<?php
// src/Controller/OrderPreviewController.php
namespace App\Controller;

use App\Entity\Order;
use App\Entity\FirstOrder;
use App\Entity\Variant;
use App\Service\OfferStrategy\OfferStrategyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\VarDumper\VarDumper;

#[Route('/orders')]
class OrderPreviewController extends AbstractController
{
    /**
     * @param OfferStrategyInterface[] $strategies
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private LoggerInterface $logger,
        private iterable $strategies,
        private \App\Repository\SettingsRepository $settingsRepository // Add this
    ) {}

    #[Route('/preview', name: 'order_preview', methods: ['POST'])]
public function preview(Request $request): JsonResponse
{
        try {
        $this->logger->info("=== PREVIEW START ===");
        $this->logger->info("Request JSON: " . $request->getContent());
        
        /** @var Order $order */
        $order = $this->serializer->deserialize(
            $request->getContent(),
            Order::class,
            'json',
            ['groups' => ['order:write']]
        );

        $this->logger->info("Order deserialized");
        $this->logger->info("Customer in order: " . ($order->getCustomer() ? 'YES - ' . $order->getCustomer()->getId() : 'NO'));
        
        // Set preview flag to true (important!)
        $order->setPreviewOrder(true);

        // Process offers first (same as OfferProcessor)
        $this->processOrder($order);
    
        // Calculate totals (no parameters needed)
        $order->calculateTotals();

        
        // Create response data
        $responseData = $this->buildPreviewResponse($order);
        
        $this->logger->info("=== PREVIEW END ===");

        return new JsonResponse($responseData, 200);

    } catch (\Exception $e) {
        $this->logger->error('Preview error: ' . $e->getMessage());
        $this->logger->error('Preview trace: ' . $e->getTraceAsString());
        return new JsonResponse([
            'error' => 'Preview calculation failed',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 400);
    }
}

    /**
     * Same processOrder logic as in OfferProcessor but with proper offer type detection
     */
    private function processOrder(Order $order): void
    {
        $this->logger->info("Processing order preview with " . $order->getOrderItem()->count() . " items");

        // Reset processed offers for all strategies
        foreach ($this->strategies as $strategy) {
            if (method_exists($strategy, 'resetProcessedOffers')) {
                $strategy->resetProcessedOffers();
            }
            if (method_exists($strategy, 'resetProcessedCustomers')) {
                $strategy->resetProcessedCustomers();
            }
        }
        
        // Clear any existing free items first (important for preview)
        $items = $order->getOrderItem()->toArray();
        foreach ($items as $item) {
            if ($item->getIsBogoFreeItem()) {
                $order->removeOrderItem($item);
            }
        }
        
        // FIRST: Set prices for all items before processing offers
        foreach ($order->getOrderItem()->toArray() as $item) {
            if ($item->getDish()) {
                $item->setPriceFromDish(); // This is CRITICAL
            }
        }
        
        // THEN: Process offers
        foreach ($order->getOrderItem()->toArray() as $item) {
            // Skip free items to avoid infinite loops
            if ($item->getIsBogoFreeItem()) {
                continue;
            }

            //$variantId = $item->getVariantId();
            // if ($variantId) {
            //     foreach ($item->getDish()->getVariants()->toArray() as $var) {
            //         if ($var->getId() == $variantId) {
            //             $priceVariant = $var->getPrice();
            //             $dish = $item->getDish();
            //             //$dish->setPrice($priceVariant);
            //         }
            //     }   
            // }

            $dish = $item->getDish();
            $bundle = $item->getBundle();
            
            if (!$dish && !$bundle) {
                continue;
            }
            
            if ($bundle) {
                continue;
            }
            
            $offer = $dish->getActiveOffer();
            if (!$offer) {
                continue;
            }

            $offerType = $this->getOfferType($offer);
            $this->logger->info("Detected offer type: " . $offerType . " for dish: " . ($dish ? $dish->getName() : 'N/A'));
            
            $item->offerType = $offerType;
            
            foreach ($this->strategies as $strategy) {
                if ($strategy->supports($offerType)) {
                    $this->logger->info("Applying strategy: " . get_class($strategy));
                    $strategy->apply($order, $item, $offer);
                    break;
                }
            }
        }

        // 🆕 FINALLY: Process Loyalty Rewards (after regular discounts)
        $this->processLoyaltyRewards($order);
        
        // Apply first order discount strategy if applicable in preview
        // For preview, we need to simulate the strategy
        $shouldApplyFirstOrder = $this->shouldApplyFirstOrderDiscount($order);
        
        if ($shouldApplyFirstOrder) {
            $this->applyFirstOrderDiscountInPreview($order);
        }
        $order->calculateTotals();
        
    }

    /**
     * 🆕 NEW: Process loyalty rewards applied to order items
     */
    private function processLoyaltyRewards(Order $order): void
    {
        $this->logger->info("=== Processing Loyalty Rewards ===");
        
        foreach ($order->getOrderItem()->toArray() as $item) {
            $appliedReward = $item->getAppliedReward();
            
            if (!$appliedReward) {
                continue;
            }
            
            $this->logger->info("Found applied reward: " . $appliedReward->getId());
            
            if ($appliedReward->getStatus() !== 'active') {
                $this->logger->error("Reward ID " . $appliedReward->getId() . " is not active (status: " . $appliedReward->getStatus() . ")");
                throw new \InvalidArgumentException('Cannot apply a reward that is not active');
            }

            // Check if reward is still active
            if ($appliedReward->getStatus() !== 'active') {
                $this->logger->info("Reward not active, skipping");
                $item->setAppliedReward(null);
                continue;
            }
            
            // Check if reward has expired
            if ($appliedReward->getExpiresAt() < new \DateTime()) {
                $this->logger->info("Reward expired, skipping");
                $item->setAppliedReward(null);
                continue;
            }
            
            $milestone = $appliedReward->getMilestone();
            $dish = $item->getDish();
            
            if (!$dish) {
                $this->logger->info("No dish associated with item, skipping");
                continue;
            }
            
            // Check if dish is eligible for this reward
            if (!$milestone->isDishEligible($dish)) {
                $this->logger->info("Dish not eligible for this reward, skipping");
                $item->setAppliedReward(null);
                continue;
            }
            
            // Apply the reward
            $rewardType = $milestone->getRewardType();
            $itemSubtotal = $item->calculateItemSubtotal();
            
            $this->logger->info("Applying reward type: " . $rewardType);
            $this->logger->info("Item subtotal: " . $itemSubtotal);
            
            if ($rewardType === 'free_service') {
                // Free item - set price to 0
                $item->setUnitPrice('0');
                $item->setTotalPrice('0');
                $this->logger->info("Applied FREE item reward");
                
            } elseif ($rewardType === 'percentage_discount') {
                // Percentage discount
                $discountPercent = $milestone->getRewardValue();
                $discount = $itemSubtotal * ($discountPercent / 100);
                $finalPrice = $itemSubtotal - $discount;
                
                $item->setTotalPrice((string) $finalPrice);
                $this->logger->info("Applied {$discountPercent}% discount, final price: {$finalPrice}");
            }
            
            // Store reward details for response
            $item->rewardDetails = [
                'id' => $appliedReward->getId(),
                'type' => $rewardType,
                'value' => $milestone->getRewardValue(),
                'description' => $milestone->getDescription()
            ];
        }
        
        $this->logger->info("=== Loyalty Rewards Processing Complete ===");
    }


    private function applyFirstOrderDiscountInPreview(Order $order): void
{
    $this->logger->info("=== Applying first order discount in preview ===");
    
    $firstOrderPercentage = $this->getFirstOrderDiscountPercentage();
    //$this->logger->info("Discount percentage: " . $firstOrderPercentage . "%");
    
    if ($firstOrderPercentage <= 0) {
        $this->logger->info("Discount percentage is 0 or negative, returning");
        return;
    }
    
    // ✅ FIX: Calculate subtotal from items
    $subtotal = 0;
    foreach ($order->getOrderItem() as $item) {
        if (!$item->getIsBogoFreeItem()) {
            $subtotal += $item->calculateItemSubtotal();
        }
    }
    
    $this->logger->info("Subtotal before discounts: " . $subtotal);
    
    if ($subtotal <= 0) {
        $this->logger->info("Subtotal is 0 or negative, returning");
        return;
    }

    // Changed to fix discount
    //$discountAmount = $subtotal * ($firstOrderPercentage / 100);
    $discountAmount = $firstOrderPercentage;
    $this->logger->info("Discount amount: " . $discountAmount);
    
    // ✅ CREATE TEMPORARY FirstOrder OBJECT
    $tempFirstOrder = new FirstOrder();
    $tempFirstOrder->setDiscountApplied(true);
    $tempFirstOrder->setDiscountValue($discountAmount);
    $order->setAppliedFirstOrderDiscount($tempFirstOrder);
    
    // ✅ The Order::calculateTotals() method will now apply the discount
    // because isFirstOrderDiscountApplied() will return true
    
    $this->logger->info("Applied first order discount in preview: {$firstOrderPercentage}% (₱{$discountAmount})");
}

    private function shouldApplyFirstOrderDiscount(Order $order): bool
{
    $this->logger->info("Checking if first order discount should apply in preview");
    
    // Check if enabled
    $firstOrderEnabled = $this->settingsRepository->getValue('first_order_enabled');
    if (!$firstOrderEnabled) {
        return false;
    }
    
    $customer = $order->getCustomer();
    if (!$customer) {
        return false;
    }
    
    // ✅ SIMPLE FIX: Check if FirstOrder record exists for this customer
    $existingFirstOrder = $this->entityManager->getRepository(FirstOrder::class)
        ->findOneBy(['customer' => $customer]);
    
    if ($existingFirstOrder && $existingFirstOrder->getDiscountApplied()) {
        $this->logger->info("Customer already used first order discount - NOT applying");
        return false;
    }
    
    $this->logger->info("Customer eligible for first order discount - applying in preview");
    return true;
}

    private function getFirstOrderDiscountPercentage(): int
    {
        // Use the correct key from database
        $percentage = $this->settingsRepository->getValue('first_order_discount_percentage');
        $this->logger->info("first_order_discount_percentage: " . ($percentage ?: 'null'));
        
        return $percentage ? (int) $percentage : 0;
    }

    private function getVariantName($variantId)
    {       
        if (!$variantId) {
            return null;
        }
        $variant = $this->entityManager->getRepository(Variant::class)->find($variantId);
        return $variant ? $variant->getSize() : null;
    }

    private function getLoyverseVariantId($variantId)
    {       
        if (!$variantId) {
            return null;
        }
        $variant = $this->entityManager->getRepository(Variant::class)->find($variantId);
        return $variant ? $variant->getLoyverseVariantId() : null;
    }

    /**
     * New method to detect offer type that works with proxy objects
     */
    private function getOfferType(object $offer): string
    {
        // Handle Doctrine proxy objects
        if ($offer instanceof \Doctrine\ORM\Proxy\Proxy) {
            // Initialize the proxy to get the real entity
            if (!$offer->__isInitialized()) {
                $offer->__load();
            }
        }
        
        // Check if this is actually a Buy X Get Y offer
        if (method_exists($offer, 'getDiscount')) {
            $discount = $offer->getDiscount();
            
            if ($discount && method_exists($discount, 'getBuyQuantity') && method_exists($discount, 'getGetQuantity')) {
                $buyQty = $discount->getBuyQuantity() ?? 0;
                $getQty = $discount->getGetQuantity() ?? 0;
                
                if ($buyQty > 0 && $getQty > 0) {
                    $this->logger->info("Detected as Buy X Get Y offer");
                    return 'buy_x_get_y';
                }
            }
            
            // Check if this is traditional BOGO
            if ($discount && method_exists($discount, 'getBogoBuyQuantity') && method_exists($discount, 'getBogoGetQuantity')) {
                $bogoBuyQty = $discount->getBogoBuyQuantity() ?? 0;
                $bogoGetQty = $discount->getBogoGetQuantity() ?? 0;
                
                if ($bogoBuyQty > 0 && $bogoGetQty > 0) {
                    $this->logger->info("Detected as traditional BOGO offer");
                    return 'bogo';
                }
            }
        }
        
        // Fallback to database type
        if (method_exists($offer, 'getType')) {
            $typeIri = $offer->getType();
            if ($typeIri) {
                try {
                    $typeId = explode('/', $typeIri)[3];
                    $offerType = $this->entityManager->getRepository(\App\Entity\OfferType::class)->find($typeId);
                    if ($offerType) {
                        $this->logger->info("Using database offer type: " . $offerType->getName());
                        return $offerType->getName();
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error getting offer type from DB: ' . $e->getMessage());
                }
            }
        }
        
        $this->logger->info("Could not determine offer type, defaulting to 'regular'");
        return 'regular';
    }

    private function buildPreviewResponse(Order $order): array
    {
        $firstOrder = $order->getAppliedFirstOrderDiscount();
        $firstOrderDiscountAmount = $firstOrder ? $firstOrder->getDiscountValue() ?? 0 : 0;
        $firstOrderApplied = $firstOrder ? ($firstOrder->getDiscountApplied() ?? false) : false;
        $firstOrderDiscountPercentage = $this->getFirstOrderDiscountPercentage();
        
        $responseData = [
        'order' => [
            'id' => null,
            'type' => $order->getOrderType(),
            'orderStatus' => 'preview',
            'subtotal' => $order->getSubtotal(),
            'deliveryFee' => $order->getDeliveryFee(),
            'totalDiscount' => $order->getTotalDiscount(),
            'taxAmount' => $order->getTaxAmount(),
            'grandTotal' => $order->getGrandTotal(),
            'orderNumber' => 'PREVIEW-' . uniqid(),
            'orderDate' => (new \DateTime())->format('Y-m-d H:i:s'),
            'orderChannel' => $order->getOrderChannel(),
            'hasFreeShipping' => $order->getHasFreeShipping(),
            'bogoItems' => $order->getBogoItems(),
            'firstOrderDiscount' => [
                'applied' => $firstOrderApplied,
                'percentage' => $firstOrderDiscountPercentage, // Use the variable
                'amount' => $firstOrderDiscountAmount
            ],
            'orderItems' => array_map(function ($item) {
                $dish = $item->getDish();
                $offerType = $item->offerType ?? null;
                
                $itemSubtotal = $item->calculateItemSubtotal();
                $totalPrice = $item->calculateTotalPrice();
                $modifierTotal = $item->getModifierTotal();
                $discountValue = $item->calculateDiscountValue() ?? 0;
                
                $itemData = [
                    'id' => null,
                    'quantity' => $item->getQuantity(),
                    'unitPrice' => $item->getUnitPrice(),
                    'itemSubtotal' => $itemSubtotal,
                    'modifierTotal' => $modifierTotal,
                    'discountApplied' => $discountValue,
                    'totalPrice' => $totalPrice,
                    'appliedDiscount' => $item->getAppliedDiscount(),
                    'isBogoFreeItem' => $item->getIsBogoFreeItem(),
                    'offerType' => $offerType,
                    'dish' => $dish ? [
                        'id' => $dish->getId(),
                        'name' => $dish->getName(),
                        'price' => $dish->getPrice(),
                        'variantId' => $item->getVariantId(), 
                        'variantName' => $this->getVariantName($item->getVariantId()),
                        'loyverseVariantId' => $this->getLoyverseVariantId($item->getVariantId()),
                        'activeOffer' => $dish->getActiveOffer() ? [
                            'id' => $dish->getActiveOffer()->getId(),
                            'name' => $dish->getActiveOffer()->getName(),
                            'type' => $offerType
                        ] : null
                    ] : null,
                    'bundle' => $item->getBundle() ? [
                        'id' => $item->getBundle()->getId(),
                        'name' => $item->getBundle()->getName(),
                        'price' => $item->getBundle()->getPrice()
                    ] : null,
                    //'appliedReward' => $item->getAppliedReward()?->getId(),
                    'appliedReward' => $item->getAppliedReward() ? [
                        'id' => $item->getAppliedReward()->getId(),
                        'milestone' => [
                            'id' => $item->getAppliedReward()->getMilestone()->getId(),
                            'name' => $item->getAppliedReward()->getMilestone()->getName(),
                            'description' => $item->getAppliedReward()->getMilestone()->getDescription(),
                            'rewardType' => $item->getAppliedReward()->getMilestone()->getRewardType(),
                            'rewardValue' => $item->getAppliedReward()->getMilestone()->getRewardValue()
                        ]
                    ] : null,

                    'lineModifiers' => $item->getLineModifiers(),
                    'customizations' => $item->getCustomizations()
                ];
                
                return $itemData;
            }, $order->getOrderItem()->toArray())
        ],
        'calculationDetails' => $order->getDiscountStatus(),
        'isPreview' => true,
        'message' => 'Preview calculation - no order has been created'
    ];


        // Add calculation breakdown
        $totalDiscount = (float)$order->getTotalDiscount();
        $itemDiscounts = $totalDiscount - $firstOrderDiscountAmount;
        $subtotalBeforeDiscounts = (float)$order->getSubtotal() + $totalDiscount;
        
        $responseData['order']['calculationBreakdown'] = [
            'subtotalBeforeDiscounts' => $subtotalBeforeDiscounts,
            'itemDiscounts' => $itemDiscounts,
            'firstOrderDiscount' => $firstOrderDiscountAmount,
            'subtotalAfterDiscount' => (float)$order->getSubtotal(),
            'taxAmount' => (float)$order->getTaxAmount(),
            'deliveryFee' => (float)$order->getDeliveryFee(),
            'grandTotal' => (float)$order->getGrandTotal()
        ];

        return $responseData;
    }

}