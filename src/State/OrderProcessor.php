<?php
// src/State/OrderProcessor.php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Settings;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class OrderProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private AuthorizationCheckerInterface $authorizationChecker,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private SettingsRepository $settingsRepository
    ) {}

    public function process($order, Operation $operation, array $uriVariables = [], array $context = [])
    {
        // Only for POST requests
        $this->logger->info("OrderProcessor started for order");
        
        if ($operation instanceof Post) {
            $user = $this->security->getUser();
            
            if (!$user) {
                throw new \RuntimeException('No authenticated user found.');
            }

            // Check if user has admin role
            $isAdmin = $this->authorizationChecker->isGranted('ROLE_ADMIN');
            
            // Get closing date from settings
            $closingDateString = $this->settingsRepository->getValue('closing_date');
            $closingDate = new \DateTime($closingDateString);
            $today = new \DateTime();
            
            $this->logger->info("Closing date: " . $closingDate->format('Y-m-d H:i:s'));
            $this->logger->info("Today: " . $today->format('Y-m-d H:i:s'));
            $this->logger->info("Is admin: " . ($isAdmin ? 'YES' : 'NO'));

            // Allow if: User is admin OR we're before closing date
            if ($isAdmin || $today != $closingDate) {
                // User is allowed to create order
                if (method_exists($user, 'getCustomer')) {
                    $order->setCustomer($user->getCustomer());
                    $this->logger->info("Order allowed for user: " . $user->getUserIdentifier());
                } else {
                    throw new \RuntimeException('The authenticated user does not have a linked Customer.');
                }
            } else {
                // Deny access - after closing date and not admin
                $this->logger->warning("Order denied - after closing date and user is not admin");
                throw new AccessDeniedException(
                    sprintf('Orders cannot be created after closing date (%s). Only administrators can create orders now.', 
                    $closingDate->format('Y-m-d H:i:s'))
                );
            }
        }

        return $this->persistProcessor->process($order, $operation, $uriVariables, $context);
    }
}