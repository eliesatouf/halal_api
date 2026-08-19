<?php
// src/Service/OfferStrategy/BuyXGetYStrategy.php

namespace App\Service\OfferStrategy;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Offer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class BuyXGetYStrategy implements OfferStrategyInterface
{
    private array $processedOffers = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}
    
    public function supports(string $offerType): bool
    {
        return $offerType === 'buy_x_get_y';
    }
    
    public function apply(Order $order, OrderItem $item, Offer $offer): void
    {
        $this->logger->error("=== BuyXGetYStrategy START ===");

        

        
        // Check if we've already processed this offer
        $offerId = $offer->getId();
        if (isset($this->processedOffers[$offerId])) {
            $this->logger->error("SKIPPING - Offer already processed");
            return;
        }

        // Mark as processed immediately
        $this->processedOffers[$offerId] = true;

        if (!$offer->isActive()) {
            $this->logger->error("Offer is not active");
            return;
        }

        $discount = $offer->getDiscount();
        if (!$discount) {
            $this->logger->error("No discount found");
            return;
        }

        // FIRST: Check if the specific offer conditions are met
        if (!$this->checkOfferConditions($order, $offer)) {
            $this->logger->error("Offer conditions not met");
            return;
        }

        // SECOND: Calculate how many free items based on the minimum quantity
        $freeItemQuantity = $this->calculateFreeItemQuantity($order, $offer);
        
        if ($freeItemQuantity <= 0) {
            $this->logger->error("No free items to add");
            return;
        }

        $freeDish = $this->getFreeDish($offer);
        if (!$freeDish) {
            $this->logger->error("No free dish available");
            return;
        }



        // Create free item
        $freeItem = new OrderItem();
        $freeItem->setDish($freeDish);
        $freeItem->setQuantity($freeItemQuantity);
        $freeItem->setUnitPrice('0');
        $freeItem->setIsBogoFreeItem(true);
        $freeItem->setBogoParentItem($item);
        $freeItem->setOrder($order);

         // Find the position of the parent item
        $orderItems = $order->getOrderItem()->toArray();
        $parentPosition = array_search($item, $orderItems, true);
        
        if ($parentPosition !== false) {
            // Insert the free item right after the parent
            $newOrderItems = [];
            foreach ($orderItems as $index => $orderItem) {
                $newOrderItems[] = $orderItem;
                if ($index === $parentPosition) {
                    $newOrderItems[] = $freeItem; // Insert free item after parent
                }
            }
            
            // Clear and re-add items in correct order
            $order->getOrderItem()->clear();
            foreach ($newOrderItems as $newItem) {
                $order->addOrderItem($newItem);
            }
        } else {
            // Fallback: just add to the end
            $order->addOrderItem($freeItem);
        }

        
        
        $this->entityManager->persist($freeItem);
        $order->addOrderItem($freeItem);
        $item->addBogoFreeItem($freeItem);
        
        $this->logger->error("Created {$freeItemQuantity} free {$freeDish->getName()}");
        $this->logger->error("=== BuyXGetYStrategy END ===");
    }

    private function countTotalEligibleQuantity(Order $order, Offer $offer): int
    {
        $eligibleDishIds = [];
        $eligibleDishes = $offer->getBogoEligibleDishes();
        
        // Get all eligible dish IDs
        foreach ($eligibleDishes as $eligibleDish) {
            $eligibleDishIds[] = $eligibleDish->getId();
        }
        
        // Count TOTAL eligible quantity (not unique types)
        $totalEligibleQuantity = 0;
        
        foreach ($order->getOrderItem() as $orderItem) {
            if ($orderItem->getIsBogoFreeItem()) {
                continue; // Skip free items
            }
            
            $dish = $orderItem->getDish();
            if (!$dish) {
                continue;
            }
            
            // Check if this dish is eligible and add its quantity
            if (in_array($dish->getId(), $eligibleDishIds)) {
                $totalEligibleQuantity += $orderItem->getQuantity();
                $this->logger->error("Eligible item: " . $dish->getName() . " x" . $orderItem->getQuantity());
            }
        }
        
        return $totalEligibleQuantity;
    }

    private function getFreeDish(Offer $offer): ?\App\Entity\Dish
    {
        $discountedDishes = $offer->getDiscountedDishes();
        if (!$discountedDishes->isEmpty()) {
            return $discountedDishes->first();
        }
        
        // If no specific free dish, use first eligible dish as fallback
        $eligibleDishes = $offer->getBogoEligibleDishes();
        if (!$eligibleDishes->isEmpty()) {
            return $eligibleDishes->first();
        }
        
        return null;
    }

    public function resetProcessedOffers(): void
    {
        $this->processedOffers = [];
    }

    private function checkOfferConditions(Order $order, Offer $offer): bool
    {
        $discount = $offer->getDiscount();
        $buyQuantity = $discount->getBuyQuantity();
        $eligibleDishes = $offer->getBogoEligibleDishes();
        
        $this->logger->error("Eligible dishes count: " . count($eligibleDishes));
        $this->logger->error("Buy quantity required: " . $buyQuantity);
        
        // SINGLE ITEM OFFER: Buy X quantity of one item, Get Y free
        if (count($eligibleDishes) === 1) {
            $eligibleDish = $eligibleDishes->first();
            $totalQuantity = 0;
            
            foreach ($order->getOrderItem() as $orderItem) {
                if ($orderItem->getIsBogoFreeItem()) {
                    continue;
                }
                
                $dish = $orderItem->getDish();
                if ($dish && $dish->getId() === $eligibleDish->getId()) {
                    $totalQuantity += $orderItem->getQuantity();
                    $this->logger->error("Found eligible item: " . $dish->getName() . " x" . $orderItem->getQuantity());
                }
            }
            
            $this->logger->error("Total quantity: " . $totalQuantity . ", Required: " . $buyQuantity);
            return $totalQuantity >= $buyQuantity;
        }

        // MULTI-ITEM OFFER: Buy X different items, Get Y free
        $foundUniqueDishes = 0;
        $foundDishIds = [];
        
        foreach ($order->getOrderItem() as $orderItem) {
            if ($orderItem->getIsBogoFreeItem()) {
                continue;
            }
            
            $dish = $orderItem->getDish();
            if (!$dish) {
                continue;
            }
            
            // Check if this dish is eligible and we haven't counted it yet
            foreach ($eligibleDishes as $eligibleDish) {
                if ($eligibleDish->getId() === $dish->getId() && 
                    $orderItem->getQuantity() >= 1 && 
                    !in_array($dish->getId(), $foundDishIds)) {
                    
                    $foundDishIds[] = $dish->getId();
                    $foundUniqueDishes++;
                    $this->logger->error("Found unique eligible dish: " . $dish->getName());
                    
                    // If we found enough different items, return true
                    if ($foundUniqueDishes >= $buyQuantity) {
                        return true;
                    }
                    break;
                }
            }
        }
        
        $this->logger->error("Found only " . $foundUniqueDishes . " unique dishes, need " . $buyQuantity);
        return false;
    }


   private function calculateFreeItemQuantity(Order $order, Offer $offer): int
    {
        $discount = $offer->getDiscount();
        $getQuantity = $discount->getGetQuantity();
        $eligibleDishes = $offer->getBogoEligibleDishes();
        
        // For single item offers
        if (count($eligibleDishes) === 1) {
            $eligibleDish = $eligibleDishes->first();
            $totalQuantity = 0;
            
            foreach ($order->getOrderItem() as $orderItem) {
                if ($orderItem->getIsBogoFreeItem()) {
                    continue;
                }
                
                $dish = $orderItem->getDish();
                if ($dish && $dish->getId() === $eligibleDish->getId()) {
                    $totalQuantity += $orderItem->getQuantity();
                }
            }
            
            $completeSets = floor($totalQuantity / $discount->getBuyQuantity());
            return $completeSets * $getQuantity;
        }
        
        // For multi-item offers: find the minimum quantity across required items
        $minQuantity = PHP_INT_MAX;
        $foundRequiredItems = 0;
        
        foreach ($eligibleDishes as $eligibleDish) {
            $itemQuantity = 0;
            
            foreach ($order->getOrderItem() as $orderItem) {
                if ($orderItem->getIsBogoFreeItem()) {
                    continue;
                }
                
                $dish = $orderItem->getDish();
                if ($dish && $dish->getId() === $eligibleDish->getId()) {
                    $itemQuantity += $orderItem->getQuantity();
                }
            }
            
            if ($itemQuantity > 0) {
                $foundRequiredItems++;
                $minQuantity = min($minQuantity, $itemQuantity);
                $this->logger->error("Dish " . $eligibleDish->getName() . " quantity: " . $itemQuantity);
            } else {
                // If any required item is missing, return 0
                $this->logger->error("Missing required dish: " . $eligibleDish->getName());
                return 0;
            }
        }
        
        // Only apply if ALL required items are present
        if ($foundRequiredItems < count($eligibleDishes)) {
            $this->logger->error("Not all required items present");
            return 0;
        }
        
        $this->logger->error("Minimum quantity: " . $minQuantity . ", Get quantity: " . $getQuantity);
        return $minQuantity * $getQuantity;
    }

}