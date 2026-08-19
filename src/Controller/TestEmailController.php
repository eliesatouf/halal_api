<?php
// src/Controller/TestEmailController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use App\Service\SettingsService;

class TestEmailController extends AbstractController
{
    #[Route('/api/test-email', name: 'test_email', methods: ['POST'])]
    public function testEmail(Request $request, MailerInterface $mailer, LoggerInterface $logger, SettingsService $settingsService): JsonResponse
    {
        $logger->error('testing123');
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['email'])) {
            return $this->json([
                'success' => false,
                'message' => 'Email address is required'
            ], 400);
        }

        $toEmail = $data['email'];
        $emailType = $data['type'] ?? 'simple';

        try {
            switch ($emailType) {
                case 'templated':
                    $email = $this->createTemplatedEmail($toEmail, $settingsService);
                    break;
                case 'simple':
                default:
                    $email = $this->createSimpleEmail($toEmail, $settingsService);
                    break;
            }

            $mailer->send($email);

            return $this->json([
                'success' => true,
                'message' => 'Test email sent successfully',
                'to' => $toEmail,
                'type' => $emailType,
                'sender' => $settingsService->get('PASSWORD_RESET') ?? 'noreply@example.com'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to send email',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    private function createSimpleEmail(string $toEmail, SettingsService $settingsService): Email
    {
        $senderEmail = $settingsService->get('PASSWORD_RESET') ?? ($settingsService->get('INFO_EMAIL') ?? 'noreply@example.com');
        $senderName = $settingsService->get('APP_NAME', 'Test Application');

        return (new Email())
            ->from(new Address($senderEmail, $senderName))
            ->to($toEmail)
            ->subject('Test Email from Symfony Application')
            ->text('This is a plain text test email.')
            ->html('
                <html>
                <body>
                    <h1>Test Email</h1>
                    <p>This is a <strong>test HTML email</strong> sent from Symfony.</p>
                    <p>If you can read this, your email configuration is working!</p>
                    <p>Timestamp: ' . date('Y-m-d H:i:s') . '</p>
                </body>
                </html>
            ');
    }

    private function createTemplatedEmail(string $toEmail, SettingsService $settingsService): TemplatedEmail
    {
        $senderEmail = $settingsService->get('PASSWORD_RESET') ?? ($settingsService->get('INFO_EMAIL') ?? 'noreply@example.com');
        $senderName = $settingsService->get('APP_NAME', 'Test Application');

        return (new TemplatedEmail())
            ->from(new Address($senderEmail, $senderName))
            ->to($toEmail)
            ->subject('Templated Test Email from Symfony')
            ->htmlTemplate('emails/test_email.html.twig')
            ->context([
                'userEmail' => $toEmail,
                'timestamp' => new \DateTime(),
                'appName' => $settingsService->get('APP_NAME', 'Symfony App'),
                'frontendUrl' => $settingsService->get('FRONTEND_URL', 'http://localhost:5173')
            ]);
    }

    #[Route('/api/test-email/config', name: 'test_email_config', methods: ['GET'])]
    public function getEmailConfig(SettingsService $settingsService): JsonResponse
    {
        $config = [
            'success' => true,
            'mailer_dsn' => $_ENV['MAILER_DSN'] ?? 'Not set',
            'sender_email' => $settingsService->get('PASSWORD_RESET', 'Not set'),
            'info_email' => $settingsService->get('INFO_EMAIL', 'Not set'),
            'frontend_url' => $settingsService->get('FRONTEND_URL', 'Not set'),
            'app_env' => $_ENV['APP_ENV'] ?? 'Not set',
            'app_name' => $settingsService->get('APP_NAME', 'Not set')
        ];

        // Hide password in DSN for security
        if (isset($config['mailer_dsn']) && $config['mailer_dsn'] !== 'Not set') {
            $config['mailer_dsn'] = preg_replace('/:[^:@]*@/', ':****@', $config['mailer_dsn']);
        }

        return $this->json($config);
    }
}