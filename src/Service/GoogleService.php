<?php
// src/Service/GoogleService.php

namespace App\Service;

use App\Entity\User;
use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class GoogleService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClientRegistry $clientRegistry,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    private function debugLog(string $message, array $context = []): void
    {
        $logDir = __DIR__ . '/../../var/log/';
        $logFile = $logDir . 'google_debug.log';
        
        // Create log directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        $logMessage = "[{$timestamp}] {$message} {$contextStr}" . PHP_EOL;
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    public function findOrCreateUserFromGoogle(GoogleUser $googleUser): User
    {
        $email = $googleUser->getEmail();
        $googleId = $googleUser->getId();
        
        if (!$email) {
            throw new \Exception('Google account does not have an email address.');
        }

        // Check by Google ID
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['googleId' => $googleId]);

        if ($existingUser) {
            $this->updateUserFromGoogle($existingUser, $googleUser);
            return $existingUser;
        }

        // Check by email
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if ($existingUser) {
            // Link existing user to Google
            $existingUser->setGoogleId($googleId);
            $this->updateUserFromGoogle($existingUser, $googleUser);
            $this->entityManager->flush();
            return $existingUser;
        }

        //Create new user
        return $this->createUserFromGoogle($googleUser);
        //return $user;
    }

    private function updateUserFromGoogle(User $user, GoogleUser $googleUser): void
    {
        if (!$user->getGivenName() && $googleUser->getFirstName()) {
            $user->setGivenName($googleUser->getFirstName());
        }
        if (!$user->getFamilyName() && $googleUser->getLastName()) {
            $user->setFamilyName($googleUser->getLastName());
        }
        
        // Update customer name if different
        if ($user->getCustomer() && $user->getCustomer()->getName() !== $user->getGivenName()) {
            $user->getCustomer()->setName($user->getGivenName());
        }
    }

    private function createUserFromGoogle(GoogleUser $googleUser): User
    {
    
        $user = new User();
        $user->setEmail($googleUser->getEmail());
        $user->setGivenName($googleUser->getFirstName() ?? $googleUser->getName());
        $user->setFamilyName($googleUser->getLastName() ?? '');
        $user->setGoogleId($googleUser->getId());
        $user->setActive(true);
        $user->setRoles(['ROLE_USER']);
        $user->setCreatedOn(new \DateTime());

        // Set avatar if available
        $avatarUrl = $googleUser->getAvatar();
        if ($avatarUrl) {
            $user->setAvatar($avatarUrl);
        }

        // Generate random password
        $randomPassword = bin2hex(random_bytes(16));
        $hashedPassword = $this->passwordHasher->hashPassword($user, $randomPassword);
        $user->setPassword($hashedPassword);
 
        $customer = new Customer();
        $customer->setEmail($googleUser->getEmail());
        $customer->setName($googleUser->getFirstName() ?? $googleUser->getName());
        $customer->setActive(true);
        $customer->setStatus('new');
        $customer->setCreatedOn(new \DateTime());
        $customer->setAddress('#NA');

        $user->setCustomer($customer);
        $customer->setUser($user);

       // $this->debugLog("💾 Starting database transaction");

        try {
            $this->entityManager->persist($user);
            
            $this->entityManager->persist($customer);
            
            $this->entityManager->flush();
            $userId = $user->getId()->toString();
            
        } catch (\Exception $e) {
            throw $e;
        }

        return $user;
    }
}