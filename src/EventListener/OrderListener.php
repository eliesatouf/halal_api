<?php

namespace App\EventListener;

use App\Entity\Order;
use App\Service\SettingsService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Order::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: Order::class)]
class OrderListener
{
    public function __construct(
        private readonly SettingsService $settingsService
    ) {
    }

    public function prePersist(Order $order, LifecycleEventArgs $args): void
    {
        $order->updateOrderItems();
        $order->onPrePersist();
        $this->calculateTotals($order);
    }

    public function preUpdate(Order $order, LifecycleEventArgs $args): void
    {
        $order->updateOrderItems();
        $order->onPreUpdate();
        $this->calculateTotals($order);
    }

    private function calculateTotals(Order $order): void
    {
        $firstOrderEnabled = $this->settingsService->getBool('FIRST_ORDER_ENABLED', false);
        $firstOrderDiscountPercentage = $this->settingsService->getInt('FIRST_ORDER_DISCOUNT_PERCENTAGE', 0);

        $order->calculateTotals($firstOrderEnabled, $firstOrderDiscountPercentage);
    }
}
