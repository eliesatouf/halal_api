<?php
// src/State/CompleteAppointmentProcessor.php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Appointment;
use App\Service\LoyaltyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class CompleteAppointmentProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor, // Use the default processor
        private EntityManagerInterface $em,
        private Security $security,
        private LoyaltyService $loyaltyService
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Appointment
    {
        $appointment = $data;
        
        // Prevent double completion
        if ($appointment->getStatus() === 'completed') {
            return $appointment;
        }

        // Update appointment
        $appointment->setStatus('completed');
        $appointment->setCompletedAt(new \DateTime());
        $appointment->setBookingStatus('completed');
        
        // Increment customer visit count
        $customer = $appointment->getCustomer();
        $customer->incrementVisitCount();
        
        // Use the default processor to persist changes
        $appointment = $this->persistProcessor->process($appointment, $operation, $uriVariables, $context);
        
        // Check for new rewards AFTER persistence
        $this->loyaltyService->checkAndCreateRewards($customer);

        if ($appointment->getAppliedReward()) {
            $this->loyaltyService->useReward($appointment->getAppliedReward());
        }
        
        return $appointment;
    }
}