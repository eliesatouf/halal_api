<?php
// src/Controller/DebugPasswordResetController.php
namespace App\Controller;

use App\Entity\PasswordResetRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response; // Added for Response return type in instruction, though JsonResponse is used

class DebugPasswordResetController extends AbstractController
{
    #[Route('/api/debug/password-reset/{email}', name: 'app_debug_password_reset')]
    public function debugPasswordReset(
        string $email,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        SettingsService $settingsService,
        LoggerInterface $logger
    ): JsonResponse {
        // The email is now passed as a route parameter, so no need to parse request body

        $logger->info('Debug password reset called', ['email' => $email]);

        // 1. Find user
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $logger->info('User not found', ['email' => $email]);
            return $this->json(['success' => true, 'message' => 'User not found (but returning success)']);
        }

        $logger->info('User found', ['user_id' => $user->getId(), 'email' => $user->getEmail()]);

        // 2. Create reset request
        $resetRequest = new PasswordResetRequest();
        $resetRequest->setEmail($email);

        $entityManager->persist($resetRequest);
        $entityManager->flush();

        $token = $resetRequest->getToken(); // Store token for use in URL

        $logger->info('Reset request created', [
            'request_id' => $resetRequest->getId(),
            'token' => $token
        ]);

        // 3. Send email
        try {
            $frontendUrl = $settingsService->get('FRONTEND_URL', 'http://localhost:5173');
            $resetUrl = $frontendUrl . '/reset-password?token=' . $token;

            $senderEmail = $settingsService->get('PASSWORD_RESET') ?? ($settingsService->get('INFO_EMAIL') ?? 'noreply@kaliboweb.com');

            $email = (new TemplatedEmail())
                ->from(new Address($senderEmail, $settingsService->get('BRAND_NAME')))
                ->to($user->getEmail())
                ->subject('Test Password Reset Request') // Changed subject as per instruction
                ->htmlTemplate('email/password_reset.html.twig')
                ->context([
                    'user' => $user,
                    'resetUrl' => $resetUrl,
                    'expiresAt' => new \DateTime('+1 hour'), // Added expiresAt as per instruction
                    'appName' => $settingsService->get('BRAND_NAME')
                ]);

            $mailer->send($email);
            $logger->info('Email sent successfully');

            return $this->json([
                'success' => true,
                'message' => 'Email sent',
                'token' => $resetRequest->getToken(),
                'reset_url' => $resetUrl
            ]);

        } catch (\Exception $e) {
            $logger->error('Failed to send email', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}