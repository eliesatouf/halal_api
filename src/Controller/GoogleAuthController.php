<?php
// src/Controller/GoogleAuthController.php

namespace App\Controller;

use App\Entity\User;
use App\Service\GoogleService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class GoogleAuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GoogleService $googleService,
        private JWTTokenManagerInterface $jwtManager
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

    #[Route('/api/auth/google', name: 'auth_google', methods: ['GET'])]
    public function connect(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('google')
            ->redirect(['email', 'profile']);
    }


    #[Route('/api/auth/google/callback', name: 'connect_google_check')]
    public function connectCheck(Request $request, ClientRegistry $clientRegistry): Response
    {
        $frontendUrl = $_ENV['FRONTEND_URL'];
        
        try {
            $client = $clientRegistry->getClient('google');
            $accessToken = $client->getAccessToken();
            $googleUser = $client->fetchUserFromToken($accessToken);

            // Find or create user
            $user = $this->googleService->findOrCreateUserFromGoogle($googleUser);
            
            // Generate PROPER JWT using lexik bundle
            $jwtToken = $this->jwtManager->create($user);
            
            // Prepare redirect
            $redirectParams = [
                'success' => 'true',
                'token' => $jwtToken, // This is now a proper JWT
                'email' => $user->getEmail(),
                'name' => $user->getGivenName() ?: $googleUser->getName(),
                'id' => $user->getId()->toString(),
                'provider' => 'google'
            ];
            
            if ($avatarUrl = $googleUser->getAvatar()) {
                $redirectParams['avatar'] = $avatarUrl;
            }
            
            $redirectUrl = $frontendUrl . '/auth/process?' . http_build_query($redirectParams);
            return $this->redirect($redirectUrl);

        } catch (\Exception $e) {
            $redirectUrl = $frontendUrl . '/auth/process?' . http_build_query([
                'success' => 'false',
                'error' => 'Authentication failed: ' . $e->getMessage()
            ]);
            return $this->redirect($redirectUrl);
        }
    }

}   