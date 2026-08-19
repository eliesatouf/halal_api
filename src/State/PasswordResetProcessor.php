<?php
// src/State/PasswordResetProcessor.php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\PasswordResetDto;
use App\Entity\PasswordResetRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class PasswordResetProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof PasswordResetDto) {
            throw new \InvalidArgumentException('Expected PasswordResetDto');
        }

        // Find the reset request
        $resetRequest = $this->entityManager->getRepository(PasswordResetRequest::class)
            ->findOneBy(['token' => $data->token]);

        if (!$resetRequest || !$resetRequest->isValid()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid or expired reset token'
            ], 400);
        }

        // Find the user
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $resetRequest->getEmail()]);

        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Update password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data->password);
        $user->setPassword($hashedPassword);

        // Mark reset request as used
        $resetRequest->setIsUsed(true);

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Password reset successfully',
            'email' => $user->getEmail()
        ], 200);
    }
}