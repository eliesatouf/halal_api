<?php
// src/Controller/FirstOrderDiscountController.php

namespace App\Controller;

use App\Entity\FirstOrder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class FirstOrderDiscountController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SettingsService $settingsService,
        private LoggerInterface $logger,
        private CacheInterface $settingsCache
    ) {}
    
    #[Route('/api/first-order-discount/check', name: 'first_order_discount_check', methods: ['GET'])]
    public function check(#[CurrentUser] $user): JsonResponse
    {
        // For logged-out users - cache this response
        if (!$user) {
            $cached = $this->settingsCache->get('first_order_discount_anonymous', function(ItemInterface $item) {
                $item->expiresAfter(0); // 5 minutes cache
                
                $this->logger->info("=== FirstOrderDiscountController check START (anonymous) ===");
                $discountPercentage = (int) $this->settingsService->get('first_order_discount_percentage', 0);
                
                // Return ARRAY, not JsonResponse
                return [
                    'eligible' => true,
                    'percentage' => $discountPercentage,
                    'message' => 'No user found',
                    'enabled' => true
                ];
            });
            
            // Convert cached array to JsonResponse
            return $this->json($cached);
        }
        
        // For logged-in users - cache per user
        $userId = $user->getId();
        $cacheKey = 'first_order_discount_user_' . $userId;
        
        $cached = $this->settingsCache->get($cacheKey, function(ItemInterface $item) use ($user) {
            $item->expiresAfter(0); // 5 minutes cache
            
            $this->logger->info("=== FirstOrderDiscountController check START for user: " . $user->getId() . " ===");
            
            $discountPercentage = (int) $this->settingsService->get('first_order_discount_percentage', 0);
            
            // Return false if user has no customer
            if (!method_exists($user, 'getCustomer') || !$user->getCustomer()) {
                $this->logger->info("User has no customer linked");
                return [
                    'eligible' => false,
                    'percentage' => 0,
                    'message' => 'No customer linked to user',
                    'enabled' => false
                ];
            }
            
            $customer = $user->getCustomer();
            $customerId = $customer->getId();
            $this->logger->info("Checking for customer ID: " . $customerId);
            
            // Check if first order discount is enabled in settings
            $firstOrderEnabled = $this->settingsService->get('first_order_enabled');
            $this->logger->info("First order enabled setting: " . ($firstOrderEnabled ?: 'null'));
            
            if (!$firstOrderEnabled ) {
                $this->logger->info("First order discount is not enabled");
                return [
                    'eligible' => false,
                    'percentage' => 0,
                    'message' => 'First order discount is not currently available',
                    'enabled' => false
                ];
            }
            
            $this->logger->info("First order discount percentage: " . $discountPercentage . "%");
            
            if ($discountPercentage <= 0) {
                $this->logger->info("Discount percentage is 0 or negative");
                return [
                    'eligible' => false,
                    'percentage' => 0,
                    'message' => 'First order discount is not currently available',
                    'enabled' => false
                ];
            }
            
            // Check FirstOrder entity
            $firstOrder = $customer->getFirstOrder();
            
            if ($firstOrder && $firstOrder->getDiscountApplied()) {
                $this->logger->info("Customer already used first order discount");
                return [
                    'eligible' => false,
                    'percentage' => $discountPercentage,
                    'message' => 'You have already used your first order discount',
                    'enabled' => true,
                    'alreadyUsed' => true,
                    'discountValue' => $firstOrder->getDiscountValue()
                ];
            }
            
            // Check for existing orders as fallback
            $hasPreviousOrders = false;
            
            foreach ($customer->getMyorder() as $order) {
                if ($order->getPreviewOrder()) continue;
                if (!$order->getOrderStatus()) continue;
                
                $status = (int) $order->getOrderStatus();
                if ($status > 1) {
                    $hasPreviousOrders = true;
                    $this->logger->info("Found completed order ID: " . $order->getId() . " with status: " . $status);
                    break;
                }
            }
            
            $eligible = !$hasPreviousOrders;
            
            $this->logger->info("Customer has previous orders: " . ($hasPreviousOrders ? 'YES' : 'NO'));
            $this->logger->info("Eligible for first order discount: " . ($eligible ? 'YES' : 'NO'));
            
            $response = [
                'eligible' => $eligible,
                'percentage' => $discountPercentage,
                'enabled' => true,
                'alreadyUsed' => false,
                'hasFirstOrderRecord' => $firstOrder ? true : false,
                'hasPreviousOrders' => $hasPreviousOrders
            ];
            
            if ($eligible) {
                $response['message'] = "Get {$discountPercentage} off on your first order!";
            } else {
                $response['message'] = $hasPreviousOrders ? 
                    'You have already placed orders before' : 
                    'Not eligible for first order discount';
            }
            
            $this->logger->info("=== FirstOrderDiscountController check END ===");
            
            return $response; // Return ARRAY, not JsonResponse
        });
        
        // Convert cached array to JsonResponse
        return $this->json($cached);
    }
    
    // Cache invalidation method
    public function invalidateUserCache($userId): void
    {
        $this->settingsCache->delete('first_order_discount_user_' . $userId);
    }
}