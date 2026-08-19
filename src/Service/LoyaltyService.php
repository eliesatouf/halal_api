<?php
// src/Service/LoyaltyService.php

namespace App\Service;

use App\Entity\Customer;
use App\Entity\LoyaltyMilestone;
use App\Entity\LoyaltyReward;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use DateTime;
use Psr\Log\LoggerInterface;

class LoyaltyService
{

    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) 
    {
        $this->logger = $logger;
    }

    public function checkAndCreateRewards(Customer $customer): void
    {
        $this->logger->info('=== checkAndCreateRewards START ===');
        $this->logger->info('Customer ID: ' . $customer->getId());
        $this->logger->info('Customer visitCount: ' . $customer->getVisitCount());
        
        $milestones = $this->em->getRepository(LoyaltyMilestone::class)
            ->findBy(['active' => true], ['visitCount' => 'ASC']);
        
        foreach ($milestones as $milestone) {
            $this->logger->info('--- Checking milestone ID: ' . $milestone->getId() . ' ---');
            
            // Check if customer already has a reward
            $existingReward = $this->em->getRepository(LoyaltyReward::class)
                ->findOneBy([
                    'customer' => $customer,
                    'milestone' => $milestone
                ]);

            if ($existingReward) {
                $this->logger->info('Reward already exists, skipping');
               // continue;
            }
           // $this->logger->info('No existing reward found');

            // Count eligible items
            $eligibleItems = $this->countEligibleItemsOrdered($customer, $milestone);

            $eligibleCount = count($eligibleItems);

            $this->logger->info('Eligible count: ' . $eligibleCount);
            $this->logger->info('Required count: ' . $milestone->getVisitCount());
            
            if ($eligibleCount >= $milestone->getVisitCount()) {
                $this->logger->info('✅ CONDITIONS MET - Creating reward!');
                $this->createReward($customer, $milestone, $eligibleItems);
                $this->logger->info('✅ Reward created');
            } else {
                $this->logger->info('❌ Conditions not met');
            }
        }
        $this->logger->info('=== checkAndCreateRewards END ===');
    }

    public function countEligibleItemsOrdered(Customer $customer, LoyaltyMilestone $milestone): array
{
    $eligibleDishIds = [];
    foreach ($milestone->getEligibleDishes() as $dish) {
        $eligibleDishIds[] = $dish->getId();
    }

    if (empty($eligibleDishIds)) {
        return [];
    }

    // Query order items directly instead of orders
    $query = $this->em->createQueryBuilder()
        ->select('oi')
        ->from('App\Entity\OrderItem', 'oi')
        ->join('oi.order', 'o')
        ->join('oi.dish', 'd')
        ->where('o.customer = :customer')
        ->andWhere('o.orderStatus = :completed')
        ->andWhere('oi.accrued IS NULL')
        ->andWhere('oi.appliedReward IS NULL')
        ->andWhere('d.id IN (:eligibleDishIds)')
        ->setParameter('customer', $customer)
        ->setParameter('completed', 5)
        ->setParameter('eligibleDishIds', $eligibleDishIds)
        ->orderBy('o.updatedOn', 'ASC')
        ->setMaxResults($milestone->getVisitCount())  // Now this limits to ITEMS, not orders
        ->getQuery();
    
    $orderItems = $query->getResult();
    $this->logger->info("##eligible order items found: " . count($orderItems));
    
    // Convert order items to the expected format
    $eligibleItems = [];
    foreach ($orderItems as $item) {
        $quantity = $item->getQuantity() ?: 1;
        for($i = 0; $i < $quantity; $i++) {
            $eligibleItems[] = $item;
        }
    }
    
    return $eligibleItems;
}

    private function createReward(Customer $customer, LoyaltyMilestone $milestone, array $orders): void
    {
        $reward = new LoyaltyReward();
        $reward->setCustomer($customer);
        $reward->setMilestone($milestone);
        $reward->setStatus('active');
        $reward->setCreatedAt(new \DateTime());
        
        // Set expiration date (3 months from now)
        $expiresAt = new \DateTime();
        $expiresAt->modify('+3 months');
        $reward->setExpiresAt($expiresAt);
        
        $this->em->persist($reward);
        $this->em->flush();

        foreach($orders as $order)
        {
            $order->setAccrued($reward->getId());
            $this->em->persist($order);
        }
        $this->em->flush();
    }

    public function useReward(LoyaltyReward $reward): bool
    {   
        if ($reward->getStatus() !== 'claimed') {
            return false;
        }

        $reward->setStatus('used');
        $this->em->flush();
        return true;
    }

    public function claimReward(LoyaltyReward $reward): bool
    {
        if ($reward->getStatus() !== 'active') {
            return false;
        }

        if ($reward->getExpiresAt() < new \DateTime()) {
            $reward->setStatus('expired');
            $this->em->flush();
            return false;
        }

        $reward->setStatus('claimed');
        $reward->setClaimedAt(new \DateTime());
        $this->em->flush();
        
        return true;
    }
}