<?php
namespace App\Service;

use App\Entity\Dish;
use App\Entity\Variant;
use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use App\Service\LoyverseTokenService;
use App\Entity\LoyverseToken;
use App\Entity\LoyversePaymentType;
use App\Entity\Modifier;
use App\Entity\ModifierOption;
use DateTime;
use DateTimeZone;
use App\Service\SettingsService;

class LoyverseSyncService
{
    private HttpClientInterface $client;
    private $accessToken; // Added property for access token if needed consistently


    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private SettingsService $settingsService
    ) {
        $this->accessToken = $this->getAccessTokenFromDatabase();
        $this->client = HttpClient::create([
            'base_uri' => 'https://api.loyverse.com/v1.0/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json',
            ],
        ]);
    }

    private function getAccessTokenFromDatabase(): string
    {
        // If we already have a valid token cached, return it
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $storeId = $this->settingsService->get('LOYVERSE_STORE_ID');
        
        if (!$storeId) {
            $this->logger->critical('LOYVERSE_STORE_ID not configured in settings');
            throw new \RuntimeException('Loyverse store ID not configured. Please configure it in settings.');
        }
        
        $token = $this->entityManager->getRepository(LoyverseToken::class)
            ->findOneBy(['storedId' => $storeId]);

        if (!$token) {
            $this->logger->error('No Loyverse token found for store: ' . $storeId);
            throw new \RuntimeException('No Loyverse token found. Please connect to Loyverse via OAuth first.');
        }

        // Check if token is expired or expires within 5 minutes
        $now = new \DateTimeImmutable();
        $expiresAt = $token->getExpiresAt();
        
        if ($expiresAt < $now->modify('+5 minutes')) {
            $this->logger->info('Loyverse token expiring soon or expired, attempting refresh', [
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'store_id' => $storeId
            ]);
            
            try {
                $this->refreshAccessToken($token);
                // Refresh the entity from database
                $this->entityManager->refresh($token);
            } catch (\Exception $e) {
                $this->logger->error('Failed to refresh Loyverse token: ' . $e->getMessage());
                throw new \RuntimeException('Access token expired and refresh failed. Please re-authenticate with Loyverse.');
            }
        }

        $this->accessToken = $token->getAccessToken();
        return $this->accessToken;
    }

    /**
     * Refresh an expired access token using refresh token
     */
    private function refreshAccessToken(LoyverseToken $token): void
    {
        $this->logger->info('Refreshing Loyverse access token');
        
        $clientId = $this->settingsService->get('LOYVERSE_CLIENT_ID');
        $clientSecret = $this->settingsService->get('LOYVERSE_CLIENT_SECRET');
        
        if (!$clientId || !$clientSecret) {
            throw new \RuntimeException('Loyverse client credentials not configured.');
        }

        $refreshToken = $token->getRefreshToken();
        if (!$refreshToken) {
            throw new \RuntimeException('No refresh token available for this store.');
        }

        $client = HttpClient::create();
        
        try {
            $response = $client->request('POST', 'https://api.loyverse.com/oauth/token', [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                ],
            ]);

            $newTokenData = $response->toArray();
            
            // Validate response
            if (!isset($newTokenData['access_token']) || !isset($newTokenData['refresh_token'])) {
                throw new \RuntimeException('Invalid token response from Loyverse.');
            }
            
            // Update token entity
            $token->setAccessToken($newTokenData['access_token']);
            $token->setRefreshToken($newTokenData['refresh_token']);
            $token->setExpiresAt(new \DateTimeImmutable('+' . $newTokenData['expires_in'] . ' seconds'));
            
            $this->entityManager->flush();
            
            $this->logger->info('Loyverse token refreshed successfully', [
                'expires_in' => $newTokenData['expires_in'],
                'store_id' => $token->getStoredId()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Loyverse token refresh failed', [
                'error' => $e->getMessage(),
                'store_id' => $token->getStoredId()
            ]);
            throw $e;
        }
    }


    /**
     * Get HTTP client with fresh token
     */
    private function getHttpClient(): HttpClientInterface
    {
        if (!$this->client) {
            $accessToken = $this->getAccessTokenFromDatabase();
            
            $this->client = HttpClient::create([
                'base_uri' => 'https://api.loyverse.com/v1.0/',
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ],
            ]);
        }
        
        return $this->client;
    }

    /**
     * Check Loyverse connection status
     */
    public function checkConnection(): array
    {
        try {
            $storeId = $this->settingsService->get('LOYVERSE_STORE_ID');
            
            if (!$storeId) {
                return [
                    'connected' => false,
                    'error' => 'LOYVERSE_STORE_ID not configured',
                    'config_status' => 'missing_store_id'
                ];
            }
            
            $token = $this->entityManager->getRepository(LoyverseToken::class)
                ->findOneBy(['storedId' => $storeId]);

            if (!$token) {
                return [
                    'connected' => false,
                    'error' => 'No token found for store: ' . $storeId,
                    'store_id' => $storeId,
                    'config_status' => 'no_token'
                ];
            }

            $now = new \DateTimeImmutable();
            $expiresAt = $token->getExpiresAt();
            $isValid = $expiresAt > $now;
            $expiresIn = $expiresAt->diff($now);
            
            return [
                'connected' => true,
                'valid' => $isValid,
                'store_id' => $storeId,
                'expires_in_human' => $expiresIn->format('%a days, %h hours, %i minutes'),
                'expires_in_seconds' => $expiresIn->s + ($expiresIn->i * 60) + ($expiresIn->h * 3600) + ($expiresIn->d * 86400),
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'needs_refresh' => $expiresAt < $now->modify('+1 hour'),
                'config_status' => 'ok'
            ];
            
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'config_status' => 'error'
            ];
        }
    }

    public function syncDishToLoyverse(Dish $dish): bool
    {
        try {
            $this->logger->info('Syncing dish to Loyverse', [
                'dish_id' => $dish->getId(),
                'name' => $dish->getName(),
                'has_loyverse_id' => !empty($dish->getLoyverseItemId())
            ]);

            // Ensure dish and variants are saved first
            // $this->entityManager->persist($dish);
            // $this->entityManager->flush();
            // $this->ensureVariantsHaveIds($dish);

            // CHECK: Does dish already exist in Loyverse?
            if ($dish->getLoyverseItemId()) {
                // UPDATE existing item
                $result = $this->updateDishInLoyverse($dish);
            } else {
                // CREATE new item
                if ($dish->hasVariant() && $dish->getVariants()->count() > 0) {
                    $result = $this->createDishWithVariants($dish);
                } else {
                    $result = $this->createSimpleDish($dish);
                }
            }

            if ($result) {
                $dish->setSyncStatus('synced');
                $dish->setSyncedAt(new \DateTimeImmutable());
            } else {
                $dish->setSyncStatus('failed');
            }

            $this->entityManager->flush();
            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to sync dish to Loyverse', [
                'dish_id' => $dish->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $dish->setSyncStatus('failed');
            $this->entityManager->flush();
            return false;
        }
    }

    private function createDishWithVariants(Dish $dish): bool
    {
        // This is your existing syncDishWithVariants method
        // Keep all your existing code, just change the name
        try {
            $this->logger->info('Creating dish with variants in Loyverse', [
                'dish_id' => $dish->getId(),
                'name' => $dish->getName(),
                'variant_count' => $dish->getVariants()->count()
            ]);

            // Check if category exists in Loyverse
            $categoryId = $dish->getCategory()?->getLoyverseCategoryId();
            if (!$categoryId) {
                $this->logger->error('Dish category not synced to Loyverse', [
                    'dish_id' => $dish->getId(),
                    'category' => $dish->getCategory()?->getName()
                ]);
                return false;
            }

            $variantData = [];

            foreach ($dish->getVariants() as $variant) {
                // Skip variants that already have Loyverse IDs
                if ($variant->getLoyverseItemId()) {
                    $this->logger->info('Variant already has Loyverse ID', [
                        'variant_id' => $variant->getId(),
                        'loyverse_id' => $variant->getLoyverseItemId()
                    ]);
                    continue;
                }

                $variantPrice = (float) $variant->getPrice();
                if ($variantPrice <= 0) {
                    $this->logger->error('Variant has invalid price', [
                        'variant_id' => $variant->getId(),
                        'size' => $variant->getSize(),
                        'price' => $variantPrice
                    ]);
                    return false;
                }

                // Generate SKU
                $sku = $this->generateSku($dish, $variant);

                $variantData[] = [
                    'sku' => $sku,
                    'option1_value' => $variant->getSize(),
                    'default_pricing_type' => 'FIXED',
                    'default_price' => $variantPrice,
                    'stores' => [
                        [
                            'store_id' => $this->settingsService->get('LOYVERSE_STORE_ID'),
                            'pricing_type' => 'FIXED',
                            'price' => $variantPrice,
                            'available_for_sale' => $dish->isActive() && $dish->isAvailable(),
                        ]
                    ]
                ];
            }

            if (empty($variantData)) {
                $this->logger->info('No variants to create for dish', ['dish_id' => $dish->getId()]);
                return true;
            }

            // Create item with variants in Loyverse
            $payload = [
                'item_name' => $dish->getName(),
                'category_id' => $categoryId,
                'option1_name' => 'Size',
                'variants' => $variantData,
            ];

            $this->logger->debug('Sending create payload to Loyverse', [
                'dish_id' => $dish->getId(),
                'payload_summary' => [
                    'item_name' => $dish->getName(),
                    'category_id' => $categoryId,
                    'variant_count' => count($variantData)
                ]
            ]);

            $response = $this->client->request('POST', 'items', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $responseContent = $response->getContent(false);

            if ($statusCode === 200) {
                $data = json_decode($responseContent, true);

                if (!isset($data['id'])) {
                    $this->logger->error('Loyverse response missing "id" field', [
                        'dish_id' => $dish->getId(),
                        'full_response' => $data
                    ]);
                    return false;
                }

                // Update dish
                $dish->setLoyverseItemId($data['id']);

                // Update variants if response contains variants
                if (isset($data['variants']) && is_array($data['variants'])) {
                    foreach ($data['variants'] as $loyverseVariant) {
                        if (!isset($loyverseVariant['id']) || !isset($loyverseVariant['sku'])) {
                            $this->logger->warning('Loyverse variant missing id or sku', [
                                'variant_data' => $loyverseVariant
                            ]);
                            continue;
                        }

                        // Find matching variant by SKU
                        foreach ($dish->getVariants() as $variant) {
                            $expectedSku = $this->generateSku($dish, $variant);
                            if ($loyverseVariant['sku'] === $expectedSku) {
                                $variant->setLoyverseItemId($loyverseVariant['id']);
                                $variant->setLoyverseSku($loyverseVariant['sku']);
                                $variant->setSyncStatus('synced');
                                $variant->setSyncedAt(new \DateTimeImmutable());
                                break;
                            }
                        }
                    }
                }

                $this->logger->info('Dish with variants created successfully', [
                    'dish_id' => $dish->getId(),
                    'loyverse_id' => $data['id'],
                    'variant_count' => count($variantData)
                ]);

                return true;

            } else {
                $errorData = json_decode($responseContent, true);
                $errorMessage = 'Unknown error';

                if (is_array($errorData)) {
                    if (isset($errorData['error'])) {
                        $errorMessage = $errorData['error'];
                    } elseif (isset($errorData['message'])) {
                        $errorMessage = $errorData['message'];
                    } else {
                        $errorMessage = json_encode($errorData);
                    }
                } else {
                    $errorMessage = $responseContent;
                }

                $this->logger->error('Loyverse API error', [
                    'dish_id' => $dish->getId(),
                    'status_code' => $statusCode,
                    'error' => $errorMessage,
                    'payload_sent' => $payload
                ]);

                return false;
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception creating dish with variants', [
                'dish_id' => $dish->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    private function updateDishInLoyverse(Dish $dish): bool
    {
        try {
            $this->logger->info('Updating dish in Loyverse', [
                'dish_id' => $dish->getId(),
                'loyverse_id' => $dish->getLoyverseItemId(),
                'name' => $dish->getName()
            ]);

            $loyverseItemId = $dish->getLoyverseItemId();

            if ($dish->hasVariant() && $dish->getVariants()->count() > 0) {
                return $this->updateDishWithVariants($dish, $loyverseItemId);
            } else {
                return $this->updateSimpleDish($dish, $loyverseItemId);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to update dish in Loyverse', [
                'dish_id' => $dish->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function updateDishWithVariants(Dish $dish, string $loyverseItemId): bool
    {
        try {
            $this->logger->info('Updating dish with variants in Loyverse', [
                'dish_id' => $dish->getId(),
                'loyverse_id' => $loyverseItemId,
                'variant_count' => $dish->getVariants()->count()
            ]);

            // Check if category exists in Loyverse
            $categoryId = $dish->getCategory()?->getLoyverseCategoryId();
            if (!$categoryId) {
                $this->logger->error('Dish category not synced to Loyverse');
                return false;
            }

            $variantData = [];

            foreach ($dish->getVariants() as $variant) {
                $variantPrice = (float) $variant->getPrice();
                if ($variantPrice <= 0) {
                    $this->logger->error('Variant has invalid price', [
                        'variant_id' => $variant->getId(),
                        'size' => $variant->getSize(),
                        'price' => $variantPrice
                    ]);
                    return false;
                }

                $variantPayload = [
                    'default_pricing_type' => 'FIXED',
                    'default_price' => $variantPrice,
                    'stores' => [
                        [
                            'store_id' => $this->settingsService->get('LOYVERSE_STORE_ID'),
                            'pricing_type' => 'FIXED',
                            'price' => $variantPrice,
                            'available_for_sale' => $dish->isActive() && $dish->isAvailable(),
                        ]
                    ]
                ];

                // If variant already has Loyverse ID, include it for update
                if ($variant->getLoyverseItemId()) {
                    $variantPayload['id'] = $variant->getLoyverseItemId();
                }

                // Include SKU for new variants
                if (!$variant->getLoyverseItemId()) {
                    $variantPayload['sku'] = $this->generateSku($dish, $variant);
                }

                $variantPayload['option1_value'] = $variant->getSize();

                $variantData[] = $variantPayload;
            }

            if (empty($variantData)) {
                $this->logger->warning('No variants to update for dish', ['dish_id' => $dish->getId()]);
                return true;
            }

            // Use POST with id field for update
            $payload = [
                'id' => $loyverseItemId, // ← THIS IS KEY: Include ID for update
                'item_name' => $dish->getName(),
                'category_id' => $categoryId,
                'option1_name' => 'Size',
                'variants' => $variantData,
            ];

            $this->logger->debug('Sending update payload to Loyverse', [
                'dish_id' => $dish->getId(),
                'loyverse_id' => $loyverseItemId,
                'variant_count' => count($variantData)
            ]);

            $response = $this->client->request('POST', 'items', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $responseContent = $response->getContent(false);

            $this->logger->info('Loyverse API Response', [
                'status_code' => $statusCode,
                'response_preview' => substr($responseContent, 0, 200)
            ]);

            if ($statusCode === 200) {
                $data = json_decode($responseContent, true);

                // Update variant IDs from response if needed
                if (isset($data['variants']) && is_array($data['variants'])) {
                    foreach ($data['variants'] as $loyverseVariant) {
                        if (!isset($loyverseVariant['id']) || !isset($loyverseVariant['option1_value'])) {
                            continue;
                        }

                        // Match by size (option1_value)
                        foreach ($dish->getVariants() as $variant) {
                            if ($variant->getSize() === $loyverseVariant['option1_value']) {
                                if (!$variant->getLoyverseItemId()) {
                                    $variant->setLoyverseItemId($loyverseVariant['id']);
                                    $variant->setLoyverseSku($loyverseVariant['sku'] ?? null);
                                    $variant->setSyncStatus('synced');
                                    $variant->setSyncedAt(new \DateTimeImmutable());
                                }
                                break;
                            }
                        }
                    }
                }

                $this->logger->info('Dish with variants updated successfully');
                return true;

            } else {
                $errorData = json_decode($responseContent, true);
                $errorMessage = 'Unknown error';

                if (is_array($errorData)) {
                    if (isset($errorData['error'])) {
                        $errorMessage = $errorData['error'];
                    } elseif (isset($errorData['message'])) {
                        $errorMessage = $errorData['message'];
                    }
                }

                $this->logger->error('Loyverse API update error', [
                    'dish_id' => $dish->getId(),
                    'status_code' => $statusCode,
                    'error' => $errorMessage
                ]);

                return false;
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception updating dish with variants', [
                'dish_id' => $dish->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function updateSimpleDish(Dish $dish, string $loyverseItemId): bool
    {
        try {
            $this->logger->info('Updating simple dish in Loyverse', [
                'dish_id' => $dish->getId(),
                'loyverse_id' => $loyverseItemId
            ]);

            $categoryId = $dish->getCategory()?->getLoyverseCategoryId();
            if (!$categoryId) {
                $this->logger->error('Dish category not synced to Loyverse');
                return false;
            }

            // Use POST with id field for update
            $payload = [
                'id' => $loyverseItemId, // ← THIS IS KEY: Include ID for update
                'item_name' => $dish->getName(),
                'category_id' => $categoryId,
                'variants' => [
                    [
                        'default_pricing_type' => 'FIXED',
                        'default_price' => (float) $dish->getPrice(),
                        'stores' => [
                            [
                                'store_id' => $this->settingsService->get('LOYVERSE_STORE_ID'),
                                'pricing_type' => 'FIXED',
                                'price' => (float) $dish->getPrice(),
                                'available_for_sale' => $dish->isActive() && $dish->isAvailable(),
                            ]
                        ]
                    ]
                ]
            ];

            $response = $this->client->request('POST', 'items', [ // ← POST, not PUT
                'json' => $payload,
            ]);

            $this->logger->info('Loyverse API Response', [
                'status_code' => $response->getStatusCode(),
                'response_preview' => substr($response->getContent(false), 0, 200)
            ]);

            if ($response->getStatusCode() === 200) {
                $this->logger->info('Simple dish updated successfully');
                return true;
            }

            $this->logger->error('Failed to update simple dish', [
                'dish_id' => $dish->getId(),
                'status' => $response->getStatusCode(),
                'response' => $response->getContent(false)
            ]);
            return false;

        } catch (\Exception $e) {
            $this->logger->error('Exception updating simple dish', [
                'dish_id' => $dish->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function findVariantBySku(Dish $dish, string $sku): ?Variant
    {
        foreach ($dish->getVariants() as $variant) {
            if ($this->generateSku($dish, $variant) === $sku) {
                return $variant;
            }
        }
        return null;
    }

    private function createSimpleDish(Dish $dish): bool
    {
        // This is your existing syncSimpleDish method
        // Just rename it
        if ($dish->getSyncStatus() === 'synced' && $dish->getLoyverseItemId()) {
            return true; // Already synced
        }

        $sku = $this->generateSku($dish);
        $payload = [
            'item_name' => $dish->getName(),
            'category_id' => $dish->getCategory()?->getLoyverseCategoryId(),
            'variants' => [
                [
                    'sku' => $sku,
                    'default_pricing_type' => 'FIXED',
                    'default_price' => (float) $dish->getPrice(),
                    'stores' => [
                        [
                            'store_id' => $this->settingsService->get('LOYVERSE_STORE_ID'), // CHANGED: Use SettingsService
                            'pricing_type' => 'FIXED',
                            'price' => (float) $dish->getPrice(),
                            'available_for_sale' => $dish->isActive() && $dish->isAvailable(),
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->client->request('POST', 'items', [
            'json' => $payload,
        ]);

        if ($response->getStatusCode() === 200) {
            $data = $response->toArray();

            // Store ITEM ID
            $dish->setLoyverseItemId($data['id']);

            // Store VARIANT ID (first variant)
            if (isset($data['variants'][0]['variant_id'])) {
                $dish->setLoyverseVariantId($data['variants'][0]['variant_id']);
            }

            $dish->setSyncStatus('synced');
            $dish->setSyncedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->logger->info('Dish synced with variant ID', [
                'dish_id' => $dish->getId(),
                'item_id' => $data['id'],
                'variant_id' => $data['variants'][0]['variant_id'] ?? null
            ]);

            return true;
        }

        $dish->setSyncStatus('failed');
        $this->entityManager->flush();
        return false;
    }


    private function generateSku(Dish $dish, ?Variant $variant = null): string
    {
        $prefix = '7000';
        $dishId = $dish->getId();

        if (!$dishId) {
            throw new \RuntimeException('Dish must be saved to database before generating SKU');
        }

        if ($variant) {
            $variantId = $variant->getId();
            if (!$variantId) {
                throw new \RuntimeException('Variant must be saved to database before generating SKU');
            }

            return sprintf('%s-D%d-V%d', $prefix, $dishId, $variantId);
        }

        return sprintf('%s-D%d', $prefix, $dishId);
    }

    private function ensureVariantsHaveIds(Dish $dish): void
    {
        foreach ($dish->getVariants() as $variant) {
            if (!$variant->getId()) {
                $this->entityManager->persist($variant);
            }
        }
        $this->entityManager->flush();
    }

    public function pushOrderToLoyverse(Order $order): bool
    {
        try {
            $client = $this->getHttpClient();
            $orderNumber = $order->getOrderNumber();

            // Ensure order number is Loyverse-compatible (max 20 chars)
            if (strlen($orderNumber) > 20) {
                $orderNumber = substr($orderNumber, 0, 20);
                $this->logger->warning('Order number truncated for Loyverse', [
                    'original' => $order->getOrderNumber(),
                    'truncated' => $orderNumber
                ]);
            }

            $this->logger->info('Pushing order to Loyverse', [
                'order_id' => $order->getId(),
                'order_number' => $orderNumber
            ]);

            $lineItems = [];
            $allDiscounts = [];

            foreach ($order->getOrderItem() as $item) {
                // Skip BOGO free items entirely (they're free)
                if ($item->getIsBogoFreeItem()) {
                    $this->logger->info('Skipping BOGO free item', [
                        'item_id' => $item->getId(),
                        'dish' => $item->getDish()?->getName()
                    ]);
                    continue;
                }

                $dish = $item->getDish();
                if (!$dish) {
                    $this->logger->warning('Order item has no dish', [
                        'item_id' => $item->getId()
                    ]);
                    continue;
                }

                // Get the correct variant ID
                $loyverseVariantId = null;

                // Check if dish has variant selected
                $variantId = $item->getVariantId();

                if ($variantId && $dish->hasVariant()) {
                    // Get variant's Loyverse ID
                    foreach ($dish->getVariants() as $variant) {
                        if ($variant->getId() == $variantId) {
                            $loyverseVariantId = $variant->getLoyverseVariantId();
                            break;
                        }
                    }
                }

                // If no variant selected or dish has no variants
                if (!$loyverseVariantId) {
                    $loyverseVariantId = $dish->getLoyverseVariantId();
                }

                if (!$loyverseVariantId) {
                    $this->logger->error('No Loyverse variant ID found for dish', [
                        'dish_id' => $dish->getId(),
                        'name' => $dish->getName()
                    ]);
                    return false;
                }

                // Get prices for this item
                $quantity = $item->getQuantity();

                // Use the FINAL unit price (after all discounts, but WITHOUT modifiers)
                $finalUnitPrice = (float) $item->getUnitPrice(); // This is base price without modifiers
                
                // Get modifier total
                $modifierTotal = $item->getModifierTotal();
                
                // Calculate total price with modifiers
                $itemSubtotal = $item->calculateItemSubtotal(); // Base + modifiers
                $lineTotal = $item->calculateTotalPrice(); // After discounts

                // Calculate modifier price per unit
                $modifierPerUnit = $modifierTotal / $quantity;
                
                // Calculate original prices
                $originalUnitPrice = (float) $dish->getPrice();
                if ($variantId) {
                    foreach ($dish->getVariants() as $variant) {
                        if ($variant->getId() == $variantId) {
                            $originalUnitPrice = (float) $variant->getPrice();
                            break;
                        }
                    }
                }

                // Original unit price with modifiers
                $originalUnitPriceWithModifiers = $originalUnitPrice + $modifierPerUnit;
                
                // Calculate discount
                $discountPerUnit = $originalUnitPriceWithModifiers - ($lineTotal / $quantity);
                $totalDiscountForItem = $discountPerUnit * $quantity;

                $lineModifiers = [];
                $modifiers = $item->getLineModifiers();

                // Build line modifiers array with correct pricing
                if (isset($modifiers) && count($modifiers) > 0) {
                    foreach ($modifiers as $modifier) {
                        $lineModifiers[] = [
                            'modifier_option_id' => $modifier['loyverseModifierOptionId'],
                            'quantity' => $quantity, // Modifiers apply to each item
                            'price' => (float) $modifier['price'] // Price per unit
                        ];
                    }
                }

                // Build line item - Send base price and modifiers separately
                $lineItem = [
                    'variant_id' => $loyverseVariantId,
                    'quantity' => $quantity,
                    'price' => round($originalUnitPrice, 2), // Base price without modifiers
                    'total_money' => round($lineTotal, 2), // Final total after all adjustments
                    'line_modifiers' => $lineModifiers
                ];

                // Add line note showing discount details (max 255 chars)
                $noteParts = [];
                
                if ($modifierTotal > 0.01) {
                    $noteParts[] = sprintf(
                        "Modifiers: +%s",
                        number_format($modifierTotal, 2)
                    );
                }
                
                if ($discountPerUnit > 0.01) { // If there's a meaningful discount
                    $noteParts[] = sprintf(
                        "Disc: -%s",
                        number_format($totalDiscountForItem, 2)
                    );
                }

                // Add customizations if any
                $customizations = $item->getCustomizations();
                if ($customizations) {
                    $customizationText = $this->formatCustomizations($item);
                    if ($customizationText) {
                        $noteParts[] = $customizationText;
                    }
                }

                if (!empty($noteParts)) {
                    $discountNote = implode(' | ', $noteParts);
                    // Truncate to 255 characters for Loyverse
                    $lineItem['line_note'] = substr($discountNote, 0, 255);
                }

                // Track this discount for receipt-level summary
                if ($totalDiscountForItem > 0.01) {
                    $allDiscounts[] = [
                        'item_name' => $dish->getName(),
                        'amount' => $totalDiscountForItem,
                        'original_total' => $originalUnitPriceWithModifiers * $quantity,
                        'final_total' => $lineTotal,
                        'modifier_total' => $modifierTotal
                    ];
                }

                $lineItems[] = $lineItem;
            }

            if (empty($lineItems)) {
                $this->logger->error('No valid items to push to Loyverse');
                return false;
            }

            $paymentType = $this->entityManager->getRepository(LoyversePaymentType::class)
                ->findOneBy(['type' => 'NONINTEGRATEDCARD']);

            $typeId = $paymentType->getLoyversePaymentTypeId();

            $date = new DateTime('now', new DateTimeZone('UTC'));
            $receipt_date = $date->format('Y-m-d\TH:i:s');

            // Build the payload
            $payload = [
                'store_id' => $this->settingsService->get('LOYVERSE_STORE_ID'),
                'pos_device_id' => '2356812d-8bbe-4bab-83de-76704aa4a327',
                'order' => $orderNumber,
                'source' => 'Website',
                'receipt_date' => $receipt_date,
                'customer_id' => $order->getCustomer()->getLoyverseCustomerId(),
                'line_items' => $lineItems,
                'payments' => [
                    [
                        'payment_type_id' => $typeId,
                        'paid_at' => $receipt_date,
                        'money_amount' => round((float) $order->getGrandTotal(), 2),
                    ]
                ],
                'note' => $this->buildOrderNote($order, $allDiscounts),
            ];

            // Add customer ID if available
            $customer = $order->getCustomer();
            if ($customer && $customer->getLoyverseCustomerId()) {
                $payload['customer_id'] = $customer->getLoyverseCustomerId();
            }

            $this->logger->debug('Sending receipt to Loyverse', [
                'order_id' => $order->getId(),
                'item_count' => count($lineItems),
                'total_discount' => array_sum(array_column($allDiscounts, 'amount')),
                'total_modifiers' => array_sum(array_map(fn($item) => $item->getModifierTotal(), $order->getOrderItem()->toArray())),
                'order_grand_total' => $order->getGrandTotal(),
                'note_length' => strlen($payload['note'])
            ]);

            // Validate note length before sending
            if (strlen($payload['note']) > 255) {
                $this->logger->warning('Note too long, truncating', [
                    'original_length' => strlen($payload['note'])
                ]);
                $payload['note'] = substr($payload['note'], 0, 255);
            }

            $response = $this->client->request('POST', 'receipts', [
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $responseContent = $response->getContent(false);

            $this->logger->info('Loyverse API Response', [
                'status_code' => $statusCode,
                'response' => $responseContent
            ]);

            if ($statusCode === 200) {
                $data = json_decode($responseContent, true);

                if (isset($data['receipt_number'])) {
                    $order->setLoyverseReceiptId($data['receipt_number']);
                    $this->entityManager->flush();

                    $this->logger->info('Order pushed to Loyverse successfully', [
                        'order_id' => $order->getId(),
                        'loyverse_receipt_id' => $data['receipt_number']
                    ]);

                    return true;
                }
            }

            $this->logger->error('Failed to push order to Loyverse', [
                'status' => $statusCode,
                'response' => $responseContent
            ]);
            return false;

        } catch (\Exception $e) {
            $this->logger->error('Exception pushing order to Loyverse', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function buildOrderNote(Order $order, array $itemDiscounts = []): string
    {
        $noteParts = [];

        // Essential info first
        $customer = $order->getCustomer();
        if ($customer) {
            $noteParts[] = 'Customer: ' . $customer->getName();
        }

        // Add order number
        $noteParts[] = 'Order: ' . $order->getOrderNumber();

        // Calculate totals including modifiers
        $totalItemDiscount = array_sum(array_column($itemDiscounts, 'amount'));
        $totalModifiers = array_sum(array_column($itemDiscounts, 'modifier_total'));
        $subtotalBeforeDiscount = (float) $order->getSubtotal() + $totalItemDiscount;

        // Add price summary (compact format)
        $priceSummary = 'Subtotal: ' . number_format((float) $order->getSubtotal(), 2);
        
        if ($totalModifiers > 0.01) {
            $priceSummary .= sprintf(
                ' (+%s modifiers)',
                number_format($totalModifiers, 2)
            );
        }
        
        if ($totalItemDiscount > 0.01) {
            $priceSummary .= sprintf(
                ' (-%s disc)',
                number_format($totalItemDiscount, 2)
            );
        }
        
        $noteParts[] = $priceSummary;

        // Add delivery fee if any
        $deliveryFee = $order->getDeliveryFee();
        if ($deliveryFee && (float) $deliveryFee > 0) {
            $noteParts[] = 'Delivery: ' . number_format((float) $deliveryFee, 2);
        }

        // Add tax if any
        $taxAmount = $order->getTaxAmount();
        if ($taxAmount && (float) $taxAmount > 0) {
            $noteParts[] = 'Tax: ' . number_format((float) $taxAmount, 2);
        }

        // Add grand total
        $noteParts[] = 'Total: ' . number_format((float) $order->getGrandTotal(), 2);

        // Join with separator
        $note = implode(' | ', $noteParts);

        // If still too long, truncate intelligently
        if (strlen($note) > 255) {
            // Remove less critical info
            if (strlen($note) > 255) {
                // Try without customer name
                $note = preg_replace('/Customer: [^|]+\|?\s*/', '', $note);
            }

            if (strlen($note) > 255) {
                // Keep only essential: Order# and Total
                $note = sprintf(
                    'Order: %s | Total: %s',
                    $order->getOrderNumber(),
                    number_format((float) $order->getGrandTotal(), 2)
                );
            }

            // Final truncation if still too long
            if (strlen($note) > 255) {
                $note = substr($note, 0, 252) . '...';
            }
        }

        return $note;
    }


    private function shouldShowDiscountAsSeparateLine(): bool
    {
        // Configure this based on your preference
        // true = shows discount as separate negative line (clearer receipt)
        // false = shows only final price with note (cleaner receipt)
        return true; // Change to false if you prefer cleaner receipts
    }

    private function createOrderNote(Order $order): string
    {
        $notes = [
            "Order #: " . $order->getOrderNumber(),
            "Customer: " . $order->getCustomer()->getName(),
        ];

        $deliveryAddress = $order->getDeliveryAddress();
        if ($deliveryAddress && is_array($deliveryAddress)) {
            if (isset($deliveryAddress['street'])) {
                $notes[] = "Address: " . $deliveryAddress['street'];
            }
        }

        return implode(' | ', $notes);
    }


    private function ensureDishInLoyverse(Dish $dish): string
    {
        // Check if already exists
        if ($dish->getLoyverseItemId()) {
            return $dish->getLoyverseItemId();
        }

        // Create simple base item with minimum price
        $payload = [
            'item_name' => $dish->getName(),
            'category_id' => $dish->getCategory()?->getLoyverseCategoryId(),
            'variants' => [
                [
                    'sku' => $this->generateSku($dish),
                    'default_pricing_type' => 'FIXED',
                    'default_price' => $this->getMinimumPrice($dish), // Cheapest option
                    'stores' => [
                        [
                            'store_id' => $this->settingsService->get('LOYVERSE_STORE_ID'),
                            'pricing_type' => 'FIXED',
                            'price' => $this->getMinimumPrice($dish),
                            'available_for_sale' => true,
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->client->request('POST', 'items', ['json' => $payload]);

        if ($response->getStatusCode() === 200) {
            $data = $response->toArray();
            $dish->setLoyverseItemId($data['id']);
            $dish->setSyncStatus('synced');
            $this->entityManager->flush();

            return $data['id'];
        }

        throw new \RuntimeException('Failed to create base item in Loyverse');
    }

    private function getMinimumPrice(Dish $dish): float
    {
        if ($dish->getHasVariant() && $dish->getVariants()->count() > 0) {
            // Find cheapest variant
            $minPrice = null;
            foreach ($dish->getVariants() as $variant) {
                if ($minPrice === null || $variant->getPrice() < $minPrice) {
                    $minPrice = $variant->getPrice();
                }
            }
            return (float) $minPrice;
        }

        return (float) $dish->getPrice();
    }

    private function createDrinkReceiptLine(OrderItem $item, float $price): array
    {
        $dish = $item->getDish();
        $baseItemId = $dish->getLoyverseItemId();

        $description = $dish->getName();

        // Add size if variant exists
        if ($item->getVariantId()) {
            $variant = $this->getVariantById($item->getVariantId());
            if ($variant) {
                $description .= ' - ' . $variant->getSize();
            }
        }

        return [
            'item_id' => $baseItemId,
            'quantity' => $item->getQuantity(),
            'price' => $price,
            'line_note' => $description,
        ];
    }



    /**
     * Fetch existing categories from Loyverse
     */
    public function fetchLoyverseCategories(): array
    {
        try {
            $client = $this->getHttpClient();
            $response = $this->client->request('GET', 'categories', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return $data['categories'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch Loyverse categories: ' . $e->getMessage());
            return [];
        }
    }

    public function syncCustomerToLoyverse($customer)
    {
        $client = $this->getHttpClient();
        //-- check if local system has loyverse customer ID
        $localLoyverseId = $customer->getLoyverseCustomerId();

        if ($localLoyverseId) {
            $this->logger->info('Customer had loyverse ID' . $localLoyverseId);
            $response = $this->client->request('GET', 'customers/' . $localLoyverseId);

            if ($response->getStatusCode() === 200) {
                $this->logger->info('cheking if loyverse ID exists in loyverse...');
                $data = $response->toArray();
                $id = $data['id'];
                $this->logger->info('ID found in loyverse');

                $updatedCustomer = $this->createCustomer($customer);
                $res = $this->updateLoyverseCustomer($updatedCustomer);
                $data = $res->toArray();
                $id = $data['id'];
                $customer->setLoyverseCustomerId($id);

            } else {
                $statusCode = $response->getStatusCode();
                $responseContent = $response->getContent(false);

                $this->logger->info('Loyverse API Error Response', [
                    'status_code' => $statusCode,
                    'response' => $responseContent
                ]);

            }
            $this->entityManager->persist($customer);
        } else {
            $newCustomer = $this->createCustomer($customer, true);
            $res = $this->updateLoyverseCustomer($newCustomer);
            $data = $res->toArray();
            $id = $data['id'];
            $customer->setLoyverseCustomerId($id);
            $this->entityManager->persist($customer);
        }

        $this->entityManager->flush();
    }

    public function createCustomer($customer, $new = false)
    {

        if ($new) {
            $newCustomer = [
                "name" => $customer->getName(),
                "email" => $customer->getEmail(),
                "phone_number" => $customer->getTelephone(),
                "address" => $customer->getAddress()
            ];
        } else {
            $newCustomer = [
                "id" => $customer->getLoyverseCustomerId(),
                "name" => $customer->getName(),
                "email" => $customer->getEmail(),
                "phone_number" => $customer->getTelephone(),
                "address" => $customer->getAddress()
            ];
        }
        $this->logger->info('creating ' . json_encode($newCustomer));
        return $customer = (object) $newCustomer;
    }

    public function updateLoyverseCustomer($customer)
    {
        $this->logger->info('updating loyverse with customer' . json_encode($customer->email));

        $response = $this->client->request('POST', 'customers', ['json' => $customer]);
        $statusCode = $response->getStatusCode();
        $responseContent = $response->getContent(false);
        $this->logger->info('Loyverse API Response', [
            'status_code' => $statusCode,
            'response' => $responseContent
        ]);
        return $response;

    }



    public function syncCategoryToLoyverse(Category $category, array $existingCategories = []): ?string
    {
        // Check if category already exists in Loyverse
        foreach ($existingCategories as $loyverseCategory) {
            if ($loyverseCategory['name'] === $category->getName()) {
                $category->setSyncStatus('synced');
                $category->setSyncedAt(new \DateTimeImmutable());
                return $loyverseCategory['id'];
            }
        }

        // Create new category
        $payload = [
            'name' => $category->getName(),
            'description' => $category->getDescription() ?? '',
        ];

        try {
            $response = $this->client->request('POST', 'categories', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();

                $category->setLoyverseCategoryId($data['id']);
                $category->setSyncStatus('synced');
                $category->setSyncedAt(new \DateTimeImmutable());

                $this->logger->info('Category synced to Loyverse', [
                    'category_id' => $category->getId(),
                    'loyverse_id' => $data['id']
                ]);

                return $data['id'];
            }

            $category->setSyncStatus('failed');
            $this->logger->error('Failed to sync category', [
                'category_id' => $category->getId(),
                'status' => $response->getStatusCode()
            ]);

            return null;

        } catch (\Exception $e) {
            $category->setSyncStatus('failed');
            $this->logger->error('Exception syncing category', [
                'category_id' => $category->getId(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function pullCategoriesFromLoyverse(): array
    {
        try {
            $client = $this->getHttpClient();
            $this->logger->info('Pulling categories from Loyverse');

            $response = $this->client->request('GET', 'categories');

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                $categories = $data['categories'] ?? [];

                $this->logger->info('Found categories in Loyverse', ['count' => count($categories)]);
                return $categories;
            }

            $this->logger->error('Failed to pull categories', ['status' => $response->getStatusCode()]);
            return [];

        } catch (\Exception $e) {
            $this->logger->error('Exception pulling categories', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function syncCategoriesFromLoyverse(): int
    {
        $loyverseCategories = $this->pullCategoriesFromLoyverse();
        $syncedCount = 0;

        foreach ($loyverseCategories as $loyverseCategory) {
            // Skip deleted categories
            if (!empty($loyverseCategory['deleted_at'])) {
                continue;
            }

            // Check if category exists locally
            $category = $this->entityManager->getRepository(Category::class)
                ->findOneBy(['loyverseCategoryId' => $loyverseCategory['id']]);

            if (!$category) {
                // Create new category
                $category = new Category();
                $category->setLoyverseCategoryId($loyverseCategory['id']);

                // Generate slug for new category
                $slug = $this->generateCategorySlug($loyverseCategory['name']);
                $category->setSlug($slug);
            }

            // Update category data
            $category->setName($loyverseCategory['name']);
            $category->setIsActive(true);
            $category->setSyncedAt(new \DateTimeImmutable());
            $category->setSyncStatus('synced');

            // Set sort order (incrementing)
            $category->setSortOrder($syncedCount + 1);

            $this->entityManager->persist($category);
            $syncedCount++;
        }

        $this->entityManager->flush();
        $this->logger->info("Synced {$syncedCount} categories from Loyverse");

        return $syncedCount;
    }

    private function generateCategorySlug(string $name): string
    {
        // Convert to lowercase
        $slug = strtolower($name);

        // Replace non-alphanumeric with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        // Trim hyphens from ends
        $slug = trim($slug, '-');

        // If empty after cleaning, use generic slug
        if (empty($slug)) {
            $slug = 'category-' . uniqid();
        }

        // Ensure uniqueness
        $baseSlug = $slug;
        $counter = 1;

        $repository = $this->entityManager->getRepository(Category::class);
        while ($repository->findOneBy(['slug' => $slug])) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }


    public function pullItemsFromLoyverse(): array
    {
        try {
            $client = $this->getHttpClient();
            $this->logger->info('Pulling items from Loyverse');

            $allItems = [];
            $cursor = null;

            do {
                $url = 'items';
                if ($cursor) {
                    $url .= '?cursor=' . urlencode($cursor);
                }

                $response = $this->client->request('GET', $url);

                if ($response->getStatusCode() === 200) {
                    $data = $response->toArray();
                    $items = $data['items'] ?? [];
                    $allItems = array_merge($allItems, $items);

                    $cursor = $data['cursor'] ?? null;
                    $this->logger->info('Fetched batch of items', ['count' => count($items), 'total' => count($allItems)]);
                } else {
                    break;
                }

            } while ($cursor);

            $this->logger->info('Total items found in Loyverse', ['count' => count($allItems)]);
            return $allItems;

        } catch (\Exception $e) {
            $this->logger->error('Exception pulling items', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function syncItemsFromLoyverse(): int
    {
        $loyverseItems = $this->pullItemsFromLoyverse();
        $syncedCount = 0;

        foreach ($loyverseItems as $loyverseItem) {
            $this->logger->info("Synced###" . json_encode($loyverseItem));
            // Find or create dish
            $dish = $this->entityManager->getRepository(Dish::class)
                ->findOneBy(['loyverseItemId' => $loyverseItem['id']]);

            if (!$dish) {
                $dish = new Dish();
                $dish->setLoyverseItemId($loyverseItem['id']);
            }

            // Update dish data
            $dish->setName($loyverseItem['item_name']);
            $dish->setDescription($loyverseItem['description'] ?? '');
            $dish->setLoyverseModifiersIds($loyverseItem['modifier_ids']);
            $dish->setActive(true);
            $dish->setMenu('main');
            $dish->setType('dining');
            $dish->setStatus('available');
            $dish->setIsAvailable(true);
            $dish->setEnableAddToCart(true);


            $this->logger->info("###category_id" . json_encode($loyverseItem['category_id']));

            // Set category if exists
            if ($loyverseItem['category_id']) {
                $category = $this->entityManager->getRepository(Category::class)
                    ->findOneBy(['loyverseCategoryId' => $loyverseItem['category_id']]);
                if ($category) {
                    $this->logger->info("###FOUNDcategory" . json_encode($category->getId()));
                    $dish->setCategory($category);
                    $this->logger->info("###categorySat");
                }
            }

            if (count($loyverseItem['variants']) == 1) {
                $dish->setHasVariant(false);
                $dish->setLoyverseVariantId($loyverseItem['variants'][0]['variant_id']);
            } else {
                $this->logger->info("###settingVariants");
                $dish->setHasVariant(true);
                $this->syncVariantsFromLoyverse($dish, $loyverseItem['variants']);
            }

            // Sync variants

            // Set price from first variant
            if (!empty($loyverseItem['variants'][0]['default_price'])) {
                $dish->setPrice((int) $loyverseItem['variants'][0]['default_price']);
            }


            $dish->setSyncedAt(new \DateTimeImmutable());
            $dish->setSyncStatus('synced');

            $this->entityManager->persist($dish);
            $syncedCount++;
        }

        $this->entityManager->flush();
        $this->logger->info("Synced {$syncedCount} items from Loyverse");

        return $syncedCount;
    }

    public function syncVariantsFromLoyverse(Dish $dish, array $loyverseVariants): void
    {
        foreach ($loyverseVariants as $loyverseVariant) {
            // Find or create variant
            $variant = $this->entityManager->getRepository(Variant::class)
                ->findOneBy(['loyverseVariantId' => $loyverseVariant['variant_id']]);
            // if($variant){
            //     $lid =$loyverseVariant['variant_id'];
            //     $this->logger->info("foundVariant for $lid");
            //     $id = $variant->getLoyverseVariantId();
            //     $this->logger->info("localVariant: $id");
            // }

            if (!$variant) {
                $variant = new Variant();
                $variant->setLoyverseVariantId($loyverseVariant['variant_id']);
                $variant->setLoyverseItemId($loyverseVariant['item_id']);
                $variant->setDish($dish);
            }

            // Update variant data
            $variant->setLoyverseVariantId($loyverseVariant['variant_id']);
            $variant->setLoyverseItemId($loyverseVariant['item_id']);
            $variant->setAvailableForSale($loyverseVariant['stores'][0]['available_for_sale']);
            $variant->setSize($loyverseVariant['option1_value'] ?? 'Regular');
            $variant->setPrice((float) $loyverseVariant['default_price']);
            $variant->setLoyverseSku($loyverseVariant['sku'] ?? '');
            $variant->setSyncStatus('synced');
            $variant->setSyncedAt(new \DateTimeImmutable());

            $this->entityManager->persist($variant);
        }
    }

    public function pullCustomersFromLoyverse(): array
    {
        try {
            $client = $this->getHttpClient();
            $this->logger->info('Pulling customers from Loyverse');

            $allCustomers = [];
            $cursor = null;

            do {
                $url = 'customers';
                if ($cursor) {
                    $url .= '?cursor=' . urlencode($cursor);
                }

                $response = $this->client->request('GET', $url);

                if ($response->getStatusCode() === 200) {
                    $data = $response->toArray();
                    $customers = $data['customers'] ?? [];
                    $allCustomers = array_merge($allCustomers, $customers);

                    $cursor = $data['cursor'] ?? null;
                    $this->logger->info('Fetched batch of customers', ['count' => count($customers), 'total' => count($allCustomers)]);
                } else {
                    break;
                }

            } while ($cursor);

            $this->logger->info('Total customers found in Loyverse', ['count' => count($allCustomers)]);
            return $allCustomers;

        } catch (\Exception $e) {
            $this->logger->error('Exception pulling customers', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function syncCustomersFromLoyverse(): int
    {
        $loyverseCustomers = $this->pullCustomersFromLoyverse();
        $syncedCount = 0;
        $client = $this->getHttpClient();
        foreach ($loyverseCustomers as $loyverseCustomer) {
            // Skip deleted customers
            if (!empty($loyverseCustomer['deleted_at'])) {
                continue;
            }

            // Check if customer exists locally
            $customer = $this->entityManager->getRepository(Customer::class)
                ->findOneBy(['loyverseCustomerId' => $loyverseCustomer['id']]);

            if (!$customer) {
                // Check by email
                if (!empty($loyverseCustomer['email'])) {
                    $customer = $this->entityManager->getRepository(Customer::class)
                        ->findOneBy(['email' => $loyverseCustomer['email']]);
                }

                if (!$customer) {
                    $customer = new Customer();
                }
            }

            // Update customer data
            $customer->setLoyverseCustomerId($loyverseCustomer['id']);
            $customer->setName($loyverseCustomer['name']);
            $customer->setEmail($loyverseCustomer['email'] ?? '');
            $customer->setTelephone($loyverseCustomer['phone_number'] ?? '');
            $customer->setAddress($loyverseCustomer['address'] ?? '');
            $customer->setActive(true);

            // Handle address
            // $address = [];
            // if (!empty($loyverseCustomer['address'])) {
            //     $address['street'] = $loyverseCustomer['address'];
            // }
            // if (!empty($loyverseCustomer['city'])) {
            //     $address['city'] = $loyverseCustomer['city'];
            // }
            // if (!empty($loyverseCustomer['region'])) {
            //     $address['region'] = $loyverseCustomer['region'];
            // }
            // if (!empty($loyverseCustomer['postal_code'])) {
            //     $address['postal_code'] = $loyverseCustomer['postal_code'];
            // }

            // if (!empty($address)) {
            //     $customer->setAddress($address);
            // }

            $this->entityManager->persist($customer);
            $syncedCount++;
        }

        $this->entityManager->flush();
        $this->logger->info("Synced {$syncedCount} customers from Loyverse");

        return $syncedCount;
    }

    public function syncPaymentTypesFromLoyverse(): int
    {
        $client = $this->getHttpClient();
        $loyversePaymentTypes = $this->pullPaymentTypesFromLoyverse();
        $syncedCount = 0;

        foreach ($loyversePaymentTypes as $loyversePaymentType) {
            $this->logger->info('### ' . json_encode($loyversePaymentType));

            $paymentType = $this->entityManager->getRepository(LoyversePaymentType::class)
                ->findOneBy(['loyversePaymentTypeId' => $loyversePaymentType['id']]);

            if (!$paymentType) {
                $this->logger->info('### NOT FOUND PAYMENT TYPE');
                $paymentType = new LoyversePaymentType();
            }
            $this->logger->info('###  FOUND PAYMENT TYPE');

            $paymentType->setName($loyversePaymentType['name']);
            $paymentType->setType($loyversePaymentType['type']);
            $paymentType->setCreatedAt(new \DateTimeImmutable($loyversePaymentType['created_at']));
            $paymentType->setUpdatedAt(new \DateTimeImmutable($loyversePaymentType['updated_at']));
            $paymentType->setDeletedAt(new \DateTimeImmutable($loyversePaymentType['deleted_at']));
            $paymentType->setLoyversePaymentTypeId($loyversePaymentType['id']);

            $this->entityManager->persist($paymentType);
            $syncedCount++;

        }
        $this->entityManager->flush();
        $this->logger->info("Synced {$syncedCount} Payment Types from Loyverse");
        return $syncedCount;
    }

    public function pullPaymentTypesFromLoyverse(): array
    {
        try {
            $client = $this->getHttpClient();
            $this->logger->info('Pulling payment types from Loyverse');

            $allPaymentTypes = [];
            $cursor = null;

            do {
                $url = 'payment_types';
                if ($cursor) {
                    $url .= '?cursor=' . urlencode($cursor);
                }

                $response = $this->client->request('GET', $url);

                if ($response->getStatusCode() === 200) {
                    $data = $response->toArray();
                    $PaymentTYpes = $data['payment_types'] ?? [];
                    $allPaymentTypes = array_merge($allPaymentTypes, $PaymentTYpes);

                    $cursor = $data['cursor'] ?? null;
                    $this->logger->info('Fetched batch of PaymentTYpes', ['count' => count($PaymentTYpes), 'total' => count($allPaymentTypes)]);
                } else {
                    break;
                }

            } while ($cursor);

            $this->logger->info('Total PaymentTYpes found in Loyverse', ['count' => count($allPaymentTypes)]);
            return $allPaymentTypes;

        } catch (\Exception $e) {
            $this->logger->error('Exception pulling PaymentTYpes', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function syncModifiersFromLoyverse(): int
    {
        
        $loyverseModifiers = $this->pullModifiersFromLoyverse();
        $syncedCount = 0;

        foreach ($loyverseModifiers as $loyverseModifier) {
            $modifier = $this->entityManager->getRepository(Modifier::class)
                ->findOneBy(['loyverseModifierId' => $loyverseModifier['id']]);

            if (!$modifier) {
                $this->logger->info('### NOT FOUND MODIFIERS');
                $modifier = new Modifier();
            }
            $this->logger->info('###  FOUND MODIFIERS');

            $modifier->setName($loyverseModifier['name']);
            $modifier->setPosition($loyverseModifier['position']);
            $modifier->setStores($loyverseModifier['stores']);
            $modifier->setCreatedAt(new \DateTimeImmutable($loyverseModifier['created_at']));
            $modifier->setUpdatedAt(new \DateTimeImmutable($loyverseModifier['updated_at']));
            $modifier->setDeletedAt($loyverseModifier['deleted_at'] ? new \DateTimeImmutable($loyverseModifier['deleted_at']) : null);
            $modifier->setLoyverseModifierId($loyverseModifier['id']);

            $this->entityManager->persist($modifier);

            // IMPORTANT: Flush here to get the modifier ID before creating options
            $this->entityManager->flush();

            if (isset($loyverseModifier['modifier_options']) && count($loyverseModifier['modifier_options']) > 0) {
                foreach ($loyverseModifier['modifier_options'] as $record) {
                    $option = $this->entityManager->getRepository(ModifierOption::class)
                        ->findOneBy(['loyverseModifierOptionId' => $record['id']]);

                    if (!$option) {
                        $option = new ModifierOption();
                        $option->setLoyverseModifierOptionId($record['id']);
                    }

                    $option->setName($record['name']);
                    $option->setPrice($record['price']);
                    $option->setPosition($record['position']);
                    $option->setCreatedAt(new \DateTimeImmutable($record['created_at']));
                    $option->setUpdatedAt(new \DateTimeImmutable($record['updated_at']));
                    $option->setDeletedAt($record['deleted_at'] ? new \DateTimeImmutable($record['deleted_at']) : null);

                    // Associate the option with the modifier
                    $option->setModifier($modifier);

                    $this->entityManager->persist($option);
                }
                $this->entityManager->flush();
            }

            $syncedCount++;
        }

        $this->logger->info("Synced {$syncedCount} Modifiers from Loyverse");
        return $syncedCount;
    }

    public function pullModifiersFromLoyverse(): array
    {
        try {
            $client = $this->getHttpClient();
            $client = $this->getHttpClient();
            $this->logger->info('Pulling modifiers from Loyverse');

            $allModifiers = [];
            $cursor = null;

            do {
                $url = 'modifiers';
                if ($cursor) {
                    $url .= '?cursor=' . urlencode($cursor);
                }

                $response = $this->client->request('GET', $url);

                //$this->logger->info('response'. $this->client->request('GET', $url));

                if ($response->getStatusCode() === 200) {
                    $data = $response->toArray();
                    $modifiers = $data['modifiers'] ?? [];

                    $this->logger->info('');

                    $allModifiers = array_merge($allModifiers, $modifiers);



                    $cursor = $data['cursor'] ?? null;
                    $this->logger->info('Fetched batch of modifiers', ['count' => count($modifiers), 'total' => count($allModifiers)]);
                } else {
                    break;
                }

            } while ($cursor);

            $this->logger->info('Total modifiers found in Loyverse', ['count' => count($allModifiers)]);
            return $allModifiers;

        } catch (\Exception $e) {
            $this->logger->error('Exception pulling modifiers', ['error' => $e->getMessage()]);
            return [];
        }
    }

}