<?php
// src/Controller/CustomerProfileController.php
namespace App\Controller;

    use App\Dto\CustomerProfile;
    use App\Entity\Customer;
    use App\Entity\LoyaltyMilestone;
    use App\Entity\LoyaltyReward;
    use App\Service\LoyaltyService; // IMPORT the service
    use Doctrine\ORM\EntityManagerInterface;
    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\Routing\Annotation\Route;
    use Symfony\Component\Serializer\SerializerInterface;
    use Symfony\Bundle\SecurityBundle\Security;
    use Psr\Log\LoggerInterface;

    #[Route('/api')]
    class CustomerProfileController extends AbstractController
    {
        public function __construct(
            private Security $security,
            private EntityManagerInterface $em,
            private SerializerInterface $serializer,
            private LoyaltyService $loyaltyService ,
            private LoggerInterface $logger

        ) 
        {
            $this->logger = $logger;
        }

        #[Route('/customer_profile', name: 'customer_profile', methods: ['GET'])]
        public function getProfile(): JsonResponse
        {
            $user = $this->security->getUser();
            $customer = $user->getCustomer();

            if (!$customer) {
                throw $this->createNotFoundException('Customer not found');
            }
            $this->loyaltyService->checkAndCreateRewards($customer);

            $profile = new CustomerProfile();
            $profile->id = $customer->getId();
            $profile->name = $customer->getName();
            $profile->email = $customer->getEmail();
            $profile->address = $customer->getAddress();
            $profile->telephone = $customer->getTelephone();
            $profile->visitCount = $customer->getVisitCount();
            
            // Get active rewards with milestone data
            $profile->activeRewards = $this->em->getRepository(LoyaltyReward::class)
                ->createQueryBuilder('r')
                ->leftJoin('r.milestone', 'm')
                ->addSelect('m')
                ->where('r.customer = :customer')
                ->andWhere('r.status = :status')
                ->andWhere('r.expiresAt > :now')
                //->andWhere('r.status != :rewardStatus')
                ->setParameter('customer', $customer)
                ->setParameter('status', 'active')
                //->setParameter('rewardStatus', 'claimed')
                ->setParameter('now', new \DateTime())
                ->getQuery()
                ->getResult();
            
            // Get all rewards for history
            $profile->rewardHistory = $this->em->getRepository(LoyaltyReward::class)
                ->createQueryBuilder('r')
                ->leftJoin('r.milestone', 'm')
                ->addSelect('m')
                ->where('r.customer = :customer')
                ->setParameter('customer', $customer)
                ->orderBy('r.createdAt', 'DESC')
                ->getQuery()
                ->getResult();

            // Get ALL milestones with status using the injected service
            $profile->allMilestones = $this->getMilestonesWithStatus($customer);

            // Serialize with the profile group
            $json = $this->serializer->serialize($profile, 'json', [
                'groups' => ['customer:profile']
            ]);

            return new JsonResponse($json, 200, [], true);
        }

        private function getMilestonesWithStatus(Customer $customer): array
        {
            // Get all active milestones
            $allMilestones = $this->em->getRepository(LoyaltyMilestone::class)
                ->findBy(['active' => true], ['visitCount' => 'ASC']);
            
            $milestonesWithStatus = [];
            $nextMilestoneId = null;
            
            foreach ($allMilestones as $milestone) {
                $requiredCount = $milestone->getVisitCount(); // Number of eligible items needed
                

                $eligibleCount = count($this->loyaltyService->countEligibleItemsOrdered($customer, $milestone));
                $this->logger->info('!!eligibleCount' .  $eligibleCount);
                $isReached = $eligibleCount >= $requiredCount;
                 
                // Check if customer has reward for this milestone
                $existingReward = $this->em->getRepository(LoyaltyReward::class)
                ->createQueryBuilder('r')
                ->where('r.customer = :customer')
                ->andWhere('r.milestone = :milestone')
                ->andWhere('r.status != :status')
                ->setParameter('customer', $customer)
                ->setParameter('milestone', $milestone)
                ->setParameter('status', 'claimed')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

                $this->logger->info('##$milestone' . json_encode($milestone->getName()));

                if($existingReward){
                    $claimed = $existingReward->getClaimedAt();
                    $this->logger->info('##claimed' . json_encode($claimed));
                }
               
                $hasReward = $existingReward !== null;
                $this->logger->info('##$hasReward' . $hasReward);

                $rewardStatus = $existingReward ? $existingReward->getStatus() : null;
                
                // Determine next milestone
                if ($nextMilestoneId === null && !$isReached && $eligibleCount === 0) {
                    $nextMilestoneId = $milestone->getId();
                }
                $isInProgress = !$isReached && $eligibleCount > 0 && !$hasReward;

                if ($hasReward && $isReached) {
                    $currentMilestoneWithReward = $milestone;
                }
                $itemsLeft = $isReached ? 0 : max(0, $requiredCount - $eligibleCount);
                
                // Calculate progress percentage

                $eligibleCount = (int) ($eligibleCount ?? 0);
                $requiredCount = (int) ($milestone->getVisitCount() ?? 1); // Prevent division by zero

                $progressPercentage = 0;
                if (!$isReached) {
                    if ($requiredCount > 0) {
                        $progressPercentage = (int) min(round(($eligibleCount / $requiredCount) * 100), 99);
                    }
                } else {
                    $progressPercentage = 100;
                }
                $progressPercentage = (int) $progressPercentage;

                //$progressPercentage = $isReached ? 100 : min(round(($eligibleCount / $requiredCount) * 100), 99);
                
                $milestonesWithStatus[] = [
                    'id' => $milestone->getId(),
                    'name' => $milestone->getName(),
                    'description' => $milestone->getDescription(),
                    'visitCount' => $requiredCount, // This is the ITEM count needed
                    'rewardType' => $milestone->getRewardType(),
                    'rewardValue' => $milestone->getRewardValue(),
                    'eligibleDishes' => array_map(function($dish) {
                        return '/api/dishes/' . $dish->getId();
                    }, $milestone->getEligibleDishes()->toArray()),
                    'active' => $milestone->isActive(),
                    
                    'isReached' => $isReached,
                    'isNext' => ($nextMilestoneId === $milestone->getId()),
                    'itemsLeft' => $itemsLeft,
                    'eligibleCount' => $eligibleCount,
                    'progressPercentage' => $progressPercentage,
                    'hasReward' => $hasReward,
                    'rewardStatus' => $rewardStatus,
                ];
            }
            
            return $milestonesWithStatus;
        }
    }