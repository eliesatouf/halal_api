<?php
// src/Controller/Api/LoyverseController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpClient\HttpClient;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\LoyverseToken;
use Psr\Log\LoggerInterface;
use App\Service\SettingsService;

class LoyverseController extends AbstractController
{
    #[Route('/api/loyverse/token', name: 'loyverse_token', methods: ['POST'])]
    public function exchangeToken(Request $request, EntityManagerInterface $entityManager, LoggerInterface $logger, SettingsService $settingsService): Response
    {
        try {
            // 1. Get the code
            $data = json_decode($request->getContent(), true);
            $code = $data['code'] ?? null;

            if (!$code) {
                return new JsonResponse(['error' => 'No code provided'], 400);
            }

            // 2. Get credentials - try different methods
            $clientId = $settingsService->get('LOYVERSE_CLIENT_ID');
            $clientSecret = $settingsService->get('LOYVERSE_CLIENT_SECRET');
            $redirectUri = $settingsService->get('LOYVERSE_REDIRECT_URI');

            if (!$clientId || !$clientSecret || !$redirectUri) {
                $logger->error("Missing config - Client ID: " . ($clientId ? 'set' : 'missing') .
                    ", Secret: " . ($clientSecret ? 'set' : 'missing') .
                    ", Redirect: " . ($redirectUri ? 'set' : 'missing'));
                return new JsonResponse([
                    'error' => 'Missing environment configuration',
                    'details' => [
                        'client_id_set' => !empty($clientId),
                        'client_secret_set' => !empty($clientSecret),
                        'redirect_uri_set' => !empty($redirectUri)
                    ]
                ], 500);
            }

            // 3. Prepare request body with proper encoding
            $params = [
                'grant_type' => 'authorization_code',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ];

            $logger->info("Redirect URI: " . $redirectUri);

            // 4. Make request to Loyverse
            $httpClient = HttpClient::create();

            $response = $httpClient->request('POST', 'https://api.loyverse.com/oauth/token', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'User-Agent' => 'Symfony HttpClient',
                ],
                'body' => $params,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $errorContent = $response->getContent(false);
                $logger->error("Loyverse API Error {$statusCode}: " . $errorContent);

                return new JsonResponse([
                    'error' => 'Loyverse API error',
                    'status_code' => $statusCode,
                    'loyverse_response' => json_decode($errorContent, true) ?: $errorContent
                ], 400);
            }

            $tokenData = $response->toArray();

            // 5. Validate required fields
            if (!isset($tokenData['access_token'])) {
                return new JsonResponse([
                    'error' => 'Invalid token response from Loyverse',
                    'response' => $tokenData
                ], 400);
            }

            // 6. Get store ID (with error handling)
            try {
                $storeId = $this->getStoreIdFromToken($tokenData['access_token']);
            } catch (\Exception $e) {
                $logger->warning("Could not get store ID: " . $e->getMessage());
                $storeId = 'unknown-store-' . uniqid();
            }

            // 7. Save to database (UPSERT - update if exists, insert if not)
            $tokenRepository = $entityManager->getRepository(LoyverseToken::class);
            $existingToken = $tokenRepository->findOneBy(['storedId' => $storeId]);

            if ($existingToken) {
                // Update existing token
                $existingToken->setAccessToken($tokenData['access_token']);
                $existingToken->setRefreshToken($tokenData['refresh_token']);
                $existingToken->setExpiresAt(new \DateTimeImmutable('+' . $tokenData['expires_in'] . ' seconds'));
                $tokenEntity = $existingToken;
            } else {
                // Create new token
                $tokenEntity = new LoyverseToken();
                $tokenEntity->setStoredId($storeId);
                $tokenEntity->setAccessToken($tokenData['access_token']);
                $tokenEntity->setRefreshToken($tokenData['refresh_token']);
                $tokenEntity->setExpiresAt(new \DateTimeImmutable('+' . $tokenData['expires_in'] . ' seconds'));
                $entityManager->persist($tokenEntity);
            }

            $entityManager->flush();

            // 8. Return success - THIS WAS MISSING!
            return new JsonResponse([
                'success' => true,
                'message' => 'Token exchanged and saved to database',
                'token_id' => $tokenEntity->getId(),
                'store_id' => $storeId,
                'expires_in' => $tokenData['expires_in'],
                'scope' => $tokenData['scope'],
                'access_token_preview' => substr($tokenData['access_token'], 0, 10) . '...',
                'refresh_token_preview' => substr($tokenData['refresh_token'], 0, 10) . '...',
                'items_fetched' => 0, // You'll need to fetch items separately
                'first_item' => null  // You'll need to fetch items separately
            ]);

        } catch (\Exception $e) {
            $logger->error("EXCEPTION in exchangeToken: " . $e->getMessage());

            return new JsonResponse([
                'error' => 'Token exchange failed',
                'message' => $e->getMessage(),
                'debug_info' => [
                    'exception_class' => get_class($e),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]
            ], 500);
        }
    }

    // Helper method to get store ID from token
    private function getStoreIdFromToken(string $accessToken): string
    {
        // Get store ID from items API (first item's store_id)
        $httpClient = HttpClient::create();

        $response = $httpClient->request('GET', 'https://api.loyverse.com/v1.0/items', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
        ]);

        $itemsData = $response->toArray();

        // Extract store ID from first item's first variant
        if (!empty($itemsData['items'][0]['variants'][0]['stores'][0]['store_id'])) {
            return $itemsData['items'][0]['variants'][0]['stores'][0]['store_id'];
        }

        // Fallback - you might need to call /stores endpoint
        return 'unknown-store-id';
    }

    #[Route('/api/loyverse/items', name: 'loyverse_items', methods: ['GET'])]
    public function fetchItems(Request $request, EntityManagerInterface $entityManager, SettingsService $settingsService): Response
    {
        try {
            // 1. Get store ID from query parameter or use default
            $storeId = $request->query->get('store_id') ?? $settingsService->get('LOYVERSE_STORE_ID');

            // 2. Get token from database
            $tokenRepository = $entityManager->getRepository(LoyverseToken::class);
            $token = $tokenRepository->findOneBy(['storedId' => $storeId]);

            if (!$token) {
                return new JsonResponse([
                    'error' => 'No token found for store',
                    'message' => 'Please connect to Loyverse first'
                ], 401);
            }

            // 3. Check if token is expired
            if ($token->getExpiresAt() < new \DateTimeImmutable()) {
                return new JsonResponse([
                    'error' => 'Token expired',
                    'message' => 'Token needs to be refreshed'
                ], 401);
            }

            // 4. Make API call with token from database
            $httpClient = HttpClient::create();

            $response = $httpClient->request('GET', 'https://api.loyverse.com/v1.0/items', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token->getAccessToken(),
                    'Accept' => 'application/json',
                ],
            ]);

            $itemsData = $response->toArray();

            // 5. Return items
            return new JsonResponse([
                'success' => true,
                'items_count' => count($itemsData['items'] ?? []),
                'store_id' => $storeId,
                'items' => $itemsData['items'] ?? [],
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to fetch items',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/loyverse/items', name: 'loyverse_create_item', methods: ['POST'])]
    public function createItem(Request $request, EntityManagerInterface $entityManager, SettingsService $settingsService): Response
    {
        try {
            // 1. Get store ID and validate
            $storeId = $request->query->get('store_id') ?? $settingsService->get('LOYVERSE_STORE_ID');

            // 2. Get token from database
            $tokenRepository = $entityManager->getRepository(LoyverseToken::class);
            $token = $tokenRepository->findOneBy(['storedId' => $storeId]);

            if (!$token) {
                return new JsonResponse(['error' => 'No token found for store'], 401);
            }

            // 3. Get item data from request
            $itemData = json_decode($request->getContent(), true);

            if (!$itemData || !isset($itemData['item_name'])) {
                return new JsonResponse(['error' => 'Invalid item data'], 400);
            }

            // 4. Make API call to Loyverse
            $httpClient = HttpClient::create();

            $response = $httpClient->request('POST', 'https://api.loyverse.com/v1.0/items', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token->getAccessToken(),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $itemData, // Symfony will automatically encode as JSON
            ]);

            $createdItem = $response->toArray();

            // 5. Return success
            return new JsonResponse([
                'success' => true,
                'message' => 'Item created successfully',
                'item' => $createdItem,
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to create item',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/debug/env', name: 'debug_env', methods: ['GET'])]
    public function debugEnv(SettingsService $settingsService): Response
    {
        return new JsonResponse([
            'db_settings' => $settingsService->getAll(),
            'env_variables' => [
                'LOYVERSE_CLIENT_ID' => $_ENV['LOYVERSE_CLIENT_ID'] ?? 'NOT SET in $_ENV',
                'LOYVERSE_CLIENT_SECRET' => isset($_ENV['LOYVERSE_CLIENT_SECRET']) ? 'SET (hidden)' : 'NOT SET',
                'LOYVERSE_REDIRECT_URI' => $_ENV['LOYVERSE_REDIRECT_URI'] ?? 'NOT SET in $_ENV',
            ],
            'getenv_variables' => [
                'LOYVERSE_CLIENT_ID' => getenv('LOYVERSE_CLIENT_ID') ?: 'NOT SET in getenv()',
                'LOYVERSE_CLIENT_SECRET' => getenv('LOYVERSE_CLIENT_SECRET') ? 'SET (hidden)' : 'NOT SET',
                'LOYVERSE_REDIRECT_URI' => getenv('LOYVERSE_REDIRECT_URI') ?: 'NOT SET in getenv()',
            ],
            'server_variables' => [
                'LOYVERSE_CLIENT_ID' => $_SERVER['LOYVERSE_CLIENT_ID'] ?? 'NOT SET in $_SERVER',
            ],
            'all_env_keys' => array_keys($_ENV),
        ]);
    }

}