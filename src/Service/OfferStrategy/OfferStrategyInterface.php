<?php

namespace App\Service\OfferStrategy;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Offer;

interface OfferStrategyInterface
{
    public function supports(string $offerType): bool;
    public function apply(Order $order, OrderItem $item, Offer $offer): void;
}