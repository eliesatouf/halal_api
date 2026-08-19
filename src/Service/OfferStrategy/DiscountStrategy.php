<?php
// api/src/Service/OfferStrategy/DiscountStrategy.php

namespace App\Service\OfferStrategy;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Offer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class DiscountStrategy implements OfferStrategyInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}
    
    public function supports(string $offerType): bool
    {
        return $offerType === Offer::TYPE_DISCOUNT; // Or use Offer::TYPE_DISCOUNT
    }
    
    public function apply(Order $order, OrderItem $item, Offer $offer): void
    {
        $this->logger->info("DiscountStrategy applied to order item");
        
        // Discount is already applied at the Dish level through calculateDiscountedPrice()
        // This strategy just ensures the discount logic is handled consistently
        
        // The actual discount calculation happens in:
        // - Dish::calculateDiscountedPrice() (for dish price)
        // - OrderItem::calculateDiscountValue() (for order item discount)
        // - Order::calculateTotals() (for order totals)
        
        // No additional action needed here since discounts are already applied
        // at the entity level calculations
    }
}