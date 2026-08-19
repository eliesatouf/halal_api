<?php

namespace App\Service\OfferStrategy;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Offer;
use App\Entity\Bundle;
use App\Entity\Dish;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

class BundleStrategy implements OfferStrategyInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function supports(string $offerType): bool
    {
        return $offerType === Offer::TYPE_BUNDLE;
    }
    
    public function apply(Order $order, OrderItem $item, Offer $offer): void
    {   
        $this->logger->error("BundleStrategy called");
        if(!$offer->isActive())
        {
            return;
        }
        
        $bundle = $offer->getBundle();
        if (!$bundle) return;
        
        // Check if the ordered item is part of this bundle
        if (!$bundle->getDishes()->contains($item->getDish())) {
            return;
        }

        $discount = $offer->getDiscount();
        if ($discount) {
            // CHECK MIN ORDER AMOUNT
            $minOrderAmount = (float) ($discount->getMinOrderAmount() ?? 0);
            if ($minOrderAmount > 0) {
                $orderSubtotal = (float) $order->getSubtotal();
                if ($orderSubtotal < $minOrderAmount) {
                    return; // Min order amount not met
                }
            }
        }
        
        // Replace individual items with bundle
        $this->replaceWithBundle($order, $item, $bundle);
    }
    
    private function replaceWithBundle(Order $order, OrderItem $triggerItem, Bundle $bundle): void
    {
        // Check if order has sufficient quantities for ALL bundle items
        foreach ($bundle->getBundleItems() as $bundleItem) {
            $requiredQty = $bundleItem->getQuantity();
            $actualQty = $this->countDishInOrder($order, $bundleItem->getDish());
            
            if ($actualQty < $requiredQty) {
                return; // Not enough items for this bundle
            }
        }

        // Remove the exact quantities needed for the bundle
        foreach ($bundle->getBundleItems() as $bundleItem) {
            $this->removeDishQuantity($order, $bundleItem->getDish(), $bundleItem->getQuantity());
        }


        
        // Add bundle item
        $bundleItem = new BundleItem();
        $bundleItem->setBundle($bundle);
        $bundleItem->setQuantity(1);
        $bundleItem->setUnitPrice($bundle->getPrice());
        $bundleItem->setOrder($order);
        $bundleItem->setOriginalPrice($bundle->getOriginalPrice());
        
        $this->entityManager->persist($bundleItem);
        $order->addOrderItem($bundleItem);

        // Recalculate totals after bundle application
        $order->calculateTotals();
    }

    // BundleStrategy.php - Add these helper methods
    private function countDishInOrder(Order $order, Dish $dish): int
    {
        $count = 0;
        foreach ($order->getOrderItem() as $item) {
            if ($item->getDish() === $dish && !$item->getBundle()) {
                $count += $item->getQuantity();
            }
        }
        return $count;
    }

    private function removeDishQuantity(Order $order, Dish $dish, int $quantityToRemove): void
    {
        $remaining = $quantityToRemove;
        
        foreach ($order->getOrderItem() as $item) {
            if ($item->getDish() === $dish && !$item->getBundle()) {
                if ($item->getQuantity() <= $remaining) {
                    $remaining -= $item->getQuantity();
                    $order->removeOrderItem($item);
                    $this->entityManager->remove($item);
                } else {
                    $item->setQuantity($item->getQuantity() - $remaining);
                    $remaining = 0;
                }
                
                if ($remaining === 0) break;
            }
        }
}
}