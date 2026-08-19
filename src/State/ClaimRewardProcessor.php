<?php
// src/State/ClaimRewardProcessor.php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\LoyaltyReward;
use App\Service\LoyaltyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ClaimRewardProcessor implements ProcessorInterface
{
    public function __construct(
        private LoyaltyService $loyaltyService,
        private EntityManagerInterface $em // ✅ Add EntityManager
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if (!$data instanceof LoyaltyReward) {
            throw new \InvalidArgumentException('Expected LoyaltyReward');
        }

        $success = $this->loyaltyService->claimReward($data);

        if (!$success) {
            throw new BadRequestHttpException('Reward cannot be claimed (already used or expired)');
        }

        // ✅ ADD THIS: Save the changes to database
        $this->em->flush();

        return $data; // Returns the updated reward
    }
}