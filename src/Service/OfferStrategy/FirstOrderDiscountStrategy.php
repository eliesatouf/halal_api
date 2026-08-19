<?php
// src/Service/OfferStrategy/FirstOrderDiscountStrategy.php

namespace App\Service\OfferStrategy;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Offer;
use App\Entity\FirstOrder;
use App\Entity\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Service\SettingsService;
use App\Entity\Customer;

class FirstOrderDiscountStrategy implements OfferStrategyInterface
{
    private array $processedCustomers = [];
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private SettingsService $settingsService
    ) {}

    public function supports(string $offerType): bool
    {
        return $offerType === 'first_order_discount';
    }

    // src/Service/OfferStrategy/FirstOrderDiscountStrategy.php
    public function apply(Order $order, OrderItem $item, ?Offer $offer = null): void
    {
        $this->logger->info("=== FirstOrderDiscountStrategy START ===");
        $this->logger->info("Order ID: " . ($order->getId() ?? 'NEW'));
        
        $customer = $order->getCustomer();
        if (!$customer) {
            $this->logger->info("No customer found");
            return;
        }
        
        $customerId = $customer->getId();
        $this->logger->info("Customer ID: " . $customerId);
        
        // Check existing orders
        $existingOrders = $this->entityManager->getRepository(Order::class)
            ->createQueryBuilder('o')
            ->where('o.customer = :customer')
            ->andWhere('o.id != :currentOrder')
            ->setParameter('customer', $customer)
            ->setParameter('currentOrder', $order->getId() ?? 0)
            ->getQuery()
            ->getResult();
            
        $this->logger->info("Existing orders count: " . count($existingOrders));

        $customer = $order->getCustomer();
        if (!$customer) {
            $this->logger->info("No customer found");
            return;
        }

        // Check if we've already processed this customer
        $customerId = $customer->getId();
        if (isset($this->processedCustomers[$customerId])) {
            $this->logger->info("Already processed customer {$customerId}");
            return;
        }
        $this->processedCustomers[$customerId] = true;

        // FIX: Use the correct keys from your database
        $firstOrderEnabled = $this->settingsService->get('first_order_enabled');
        $this->logger->info("FIRST_ORDER_ENABLED value: " . ($firstOrderEnabled ?: 'null'));
        
        // Check if enabled (should be string 'true')
        if (!$firstOrderEnabled || strtolower($firstOrderEnabled) !== 'true') {
            $this->logger->info("First order discount not enabled $firstOrderEnabled ");
            $this->logger->info("=== FirstOrderDiscountStrategy END (not enabled) ===");
            return;
        }

        // FIX: Use the correct key
        $discountPercentage = (int) $this->settingsService->get('first_order_discount_percentage', 0);
        $this->logger->info("FIRST_ORDER_DISCOUNT_PERCENTAGE value: " . $discountPercentage);
        
        if ($discountPercentage <= 0) {
            $this->logger->info("Discount percentage is 0 or negative");
            $this->logger->info("=== FirstOrderDiscountStrategy END (0%) ===");
            return;
        }

        // Check existing FirstOrder record
        $firstOrder = $customer->getFirstOrder();
        if ($firstOrder && $firstOrder->getDiscountApplied()) {
            $this->logger->info("Customer {$customerId} already used first order discount");
            $this->logger->info("=== FirstOrderDiscountStrategy END (already used) ===");
            return;
        }

        $existingOrders = $this->entityManager->getRepository(Order::class)
            ->findBy(['customer' => $customer]);
        if (count($existingOrders) > 0) {
            $this->logger->info("Customer {$customerId} already has previous orders");
            $this->logger->info("=== FirstOrderDiscountStrategy END (has previous orders) ===");
            return;
        }


        $this->logger->info("Customer {$customerId} is eligible for first order discount");
        
        // Apply discount directly to order totals
        $this->applyDiscountToOrder($order, $discountPercentage, $customer);
        
        $this->logger->info("=== FirstOrderDiscountStrategy END (applied) ===");
    }


    private function applyDiscountToOrder(Order $order, int $discountPercentage, Customer $customer): void
    {
        $this->logger->info("Applying discount to order: {$discountPercentage}%");
        
        // Calculate discount based on subtotal BEFORE any discounts
        $subtotalBeforeDiscounts = 0;
        
        foreach ($order->getOrderItem() as $item) {
            if ($item->getIsBogoFreeItem()) {
                continue;
            }
            $subtotalBeforeDiscounts += $item->calculateItemSubtotal();
        }

        $this->logger->info("Subtotal before discounts: " . $subtotalBeforeDiscounts);
        
        if ($subtotalBeforeDiscounts <= 0) {
            $this->logger->info("Subtotal is 0 or negative, skipping");
            return;
        }

        //$discountAmount = $subtotalBeforeDiscounts * ($discountPercentage / 100);
        // Changing to fixed amount discount 
        $discountAmount = $subtotalBeforeDiscounts - $discountPercentage;
        $this->logger->info("Discount amount: " . $discountAmount);
        
        // Create/update FirstOrder record
        $firstOrder = $customer->getFirstOrder();
        if (!$firstOrder) {
            $firstOrder = new FirstOrder();
            $firstOrder->setCustomer($customer);
            $firstOrder->setDiscountApplied(true);
            $firstOrder->setDiscountValue($discountAmount); // Store the actual discount amount
            $customer->setFirstOrder($firstOrder);
            $this->logger->info("Created new FirstOrder record with discount: " . $discountAmount);
        } else {
            $firstOrder->setDiscountApplied(true);
            $firstOrder->setDiscountValue($discountAmount);
            $this->logger->info("Updated existing FirstOrder record with discount: " . $discountAmount);
        }
        
        // Link the FirstOrder to the Order
        $order->setAppliedFirstOrderDiscount($firstOrder);
        
        $this->entityManager->persist($firstOrder);
        $this->logger->info("Persisted FirstOrder entity");
        
        $this->logger->info("Applied first order discount: {$discountPercentage}% (\${$discountAmount})");
    }

    public function resetProcessedCustomers(): void
    {
        $this->processedCustomers = [];
    }



}