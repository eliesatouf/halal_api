<?php
// src/Service/MessageGenerator.php
namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;


class EmailService
{
  private $mailer;
  private $settingsService;

  public function __construct(MailerInterface $mailer, SettingsService $settingsService)
  {
    $this->mailer = $mailer;
    $this->settingsService = $settingsService;
  }

  public function sendMail($mailTo, $serviceName, $body, $date, $time): string
  {

    $contactEmail = $this->settingsService->get('CONTACT_EMAIL');
    $contactCCEmail = $this->settingsService->get('CONTACT_CC_EMAIL');

    $units = '';
    $email = (new TemplatedEmail())
      ->from($contactEmail)
      ->to($contactEmail)
      ->subject("Inquery $serviceName")
      ->html($body);

    if($contactCCEmail)
    {
      $email->cc($contactCCEmail);
    }

    $email->getHeaders()
      ->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');

    $res = 1;
    try {
      $this->mailer->send($email);
    } catch (TransportExceptionInterface $e) {
      if ($e)
        $res = $e;
    }
    return $res;
  }
}