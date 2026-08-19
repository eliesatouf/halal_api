<?php
// src/Service/OfferStrategy/BogoStrategy.php

namespace App\Service\OfferStrategy;

use App\Entity\Order;   
use App\Entity\OrderItem;
use App\Entity\Offer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class BogoStrategy implements OfferStrategyInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}
    
    public function supports(string $offerType): bool
    {
        return $offerType === 'bogo'; // Only support traditional BOGO, not buy_x_get_y
    }

    private array $processedOffers = [];

    public function resetProcessedOffers(): void
    {
        $this->processedOffers = [];
    }

    public function apply(Order $order, OrderItem $item, Offer $offer): void
    {
        $this->logger->error("BogoStrategy called - checking if this is traditional BOGO");

        $this->logger->error("=== BogoStrategy START ===");
    
    // Safety check: Only process if this is actually traditional BOGO
    $discount = $offer->getDiscount();
    if (!$discount || !$discount->getBogoBuyQuantity() || !$discount->getBogoGetQuantity()) {
        $this->logger->error("SKIPPING - Not a valid BOGO offer");
        return;
    }

        $offerId = $offer->getId();
        if (isset($this->processedOffers[$offerId])) {
            $this->logger->error("BOGO Offer {$offerId} already processed for this order");
            return;
        }


        // FIRST: Check if this is actually a Buy X Get Y offer and skip it
        $discount = $offer->getDiscount();
        if ($discount) {
            $buyQuantity = $discount->getBuyQuantity() ?? 0;
            $getQuantity = $discount->getGetQuantity() ?? 0;
            
            // If this has Buy X Get Y quantities, it's not a traditional BOGO
            if ($buyQuantity > 0 && $getQuantity > 0) {
                $this->logger->error("Skipping - this is a Buy X Get Y offer, not traditional BOGO");
                return;
            }
        }

        if(!$offer->isActive())
        {
            $this->logger->error("Offer is not active");
            return;
        }

        $dish = $item->getDish();
        if (!$dish) {
            $this->logger->error("No dish found for item");
            return;
        }
        
        if (!$offer->getIsBogo()) {
            $this->logger->error("Not a BOGO offer");
            return;
        }

        $discount = $offer->getDiscount();
        if (!$discount) {
            $this->logger->error("No discount found for offer");
            return;
        }

        $minOrderAmount = (float) ($discount->getMinOrderAmount() ?? 0);
       
        if ($minOrderAmount > 0) {
            $currentSubtotal = 0;
            foreach ($order->getOrderItem() as $orderItem) {
                if (!$orderItem->getIsBogoFreeItem() ) {
                    if($orderItem->getDish())
                    {
                        $currentSubtotal += $orderItem->getDish()->getPrice() * $orderItem->getQuantity();
                    }
                }
            }
            $this->logger->error("Calculated subtotal for check: ". $currentSubtotal);
            if ($currentSubtotal < $minOrderAmount) {
                $this->logger->error("Min order amount not met");
                return;
            }
        }

        // Get bogoType from OFFER (not discount)
        $bogoType = $offer->getBogoType();
        if (!$bogoType) {
            $this->logger->error("No BOGO type defined");
            return;
        }

        
        $buyQty = $discount ? ($discount->getBogoBuyQuantity() ?? 1) : 1;
        $getQty = $discount ? ($discount->getBogoGetQuantity() ?? 1) : 1;
        
        // Check eligibility based on BOGO type
        $isEligible = false;
        $freeDish = null;
        
        if ($bogoType === 'same') {
            // For same type: dish must be in eligible dishes
            $isEligible = $offer->getBogoEligibleDishes()->contains($dish);
            $freeDish = $dish; // Same dish for free item
        } 
        elseif ($bogoType === 'free_item') {
            // For free_item: dish must be in eligible dishes (buy item)
            // and there should be discounted dishes (free items)
            $isEligible = $offer->getBogoEligibleDishes()->contains($dish) 
                        && !$offer->getDiscountedDishes()->isEmpty();
            
            // Use first discounted dish as free item
            $freeDish = $offer->getDiscountedDishes()->first() ?: null;
        }
        
        if (!$isEligible || !$freeDish) {
            $this->logger->error("Dish not eligible or no free dish available");
            return;
        }
        
        // Calculate free quantity
        $freeQty = floor($item->getQuantity() / $buyQty) * $getQty;
        
        if ($freeQty <= 0) {
            $this->logger->error("No free items to add");
            return;
        }



        // Create free item with proper quantity
        $freeItem = new OrderItem();
        $freeItem->setDish($freeDish);
        $freeItem->setQuantity($freeQty);
        $freeItem->setUnitPrice('0');
        $freeItem->setIsBogoFreeItem(true);
        $freeItem->setBogoParentItem($item);
        $freeItem->setOrder($order);
        
        $this->entityManager->persist($freeItem);
        $order->addOrderItem($freeItem);
        $item->addBogoFreeItem($freeItem);

        $this->processedOffers[$offerId] = true;

        $this->logger->error("BogoStrategy added " . $freeQty . " free items");
    }
}