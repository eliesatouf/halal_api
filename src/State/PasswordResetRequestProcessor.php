<?php
// src/State/PasswordResetRequestProcessor.php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\PasswordResetRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;
use App\Service\SettingsService;

final class PasswordResetRequestProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly SettingsService $settingsService
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $this->logger->info('=== PROCESSOR START ===');

        if (!$data instanceof PasswordResetRequest) {
            throw new \InvalidArgumentException('Expected PasswordResetRequest');
        }

        $email = $data->getEmail();
        $this->logger->info('Email from request: ' . $email);

        // 1. Check if user exists
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if (!$user) {
            $this->logger->info('User not found');
            return new JsonResponse([
                'success' => true,
                'message' => 'If the email exists, a reset link has been sent'
            ], 201);
        }

        $this->logger->info('User found: ' . $user->getEmail());

        // 2. Save token to DB
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        $token = $data->getToken();
        $this->logger->info('Token saved: ' . $token);
        $this->logger->info('Entity ID: ' . $data->getId());

        // 3. Send email WITH TEMPLATE
        try {
            $frontendUrl = $this->settingsService->get('FRONTEND_URL', 'https://www.sandyscup.com');
            $resetUrl = $frontendUrl . '/reset-password?token=' . $token;

            $senderEmail = $this->settingsService->get('PASSWORD_RESET') ?? ($this->settingsService->get('INFO_EMAIL'));
            $senderName = $this->settingsService->get('APP_NAME');

            // $this->logger->info('Attempting to send email to: ' . $user->getEmail());
            // $this->logger->info('Sender: ' . $senderEmail);
            // $this->logger->info('Reset URL: ' . $resetUrl);
            // $this->logger->info('Template: email/password_reset.html.twig');

            // Check if template exists
            $templatePath = __DIR__ . '/../../templates/email/password_reset.html.twig';
            if (!file_exists($templatePath)) {
                $this->logger->warning('Template not found: ' . $templatePath);
                // Fall back to simple email
                $email = (new \Symfony\Component\Mime\Email())
                    ->from($senderEmail)
                    ->to($user->getEmail())
                    ->subject('Password Reset Request - ' . ($this->settingsService->get('APP_NAME')))
                    ->text('Reset your password: ' . $resetUrl)
                    ->html('<p>Reset your password: <a href="' . $resetUrl . '">' . $resetUrl . '</a></p>');
            } else {
                // Use template
                $email = (new TemplatedEmail())
                    ->from(new Address($senderEmail, $senderName))
                    ->to($user->getEmail())
                    ->subject('Password Reset Request - ' . ($this->settingsService->get('APP_NAME')))
                    ->htmlTemplate('email/password_reset.html.twig')
                    ->context([
                        'user' => $user,
                        'resetToken' => $token,
                        'resetUrl' => $resetUrl,
                        'expiresAt' => $data->getExpiresAt(),
                        'appName' => $this->settingsService->get('APP_NAME')
                    ]);
            }

            $this->mailer->send($email);
            $this->logger->info('✅ EMAIL WITH TEMPLATE SENT SUCCESSFULLY');

        } catch (\Exception $e) {
            $this->logger->error('❌ EMAIL FAILED: ' . $e->getMessage());
            $this->logger->error('Trace: ' . $e->getTraceAsString());
        }

        $this->logger->info('=== PROCESSOR END ===');

        return new JsonResponse([
            'success' => true,
            'message' => 'If the email exists, a reset link has been sent',
            'email' => $email
        ], 201);
    }
}