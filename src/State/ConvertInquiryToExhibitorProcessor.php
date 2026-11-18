<?php
// src/State/ConvertInquiryToExhibitorProcessor.php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Exhibitor;
use App\Entity\Inquiry;
use App\Repository\InquiryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ConvertInquiryToExhibitorProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private InquiryRepository $inquiries,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Exhibitor
    {
        $inquiry = $this->inquiries->find($uriVariables['id'] ?? null);
        if (!$inquiry) {
            throw new NotFoundHttpException('Inquiry not found');
        }

        // Determine default category based on inquiry type
        $category = match ($inquiry->getType()) {
            Inquiry::TYPE_EXHIBITOR    => Exhibitor::CATEGORY_MSME, // safe default; user can adjust
            Inquiry::TYPE_PARTNER      => Exhibitor::CATEGORY_EMBASSY,
            Inquiry::TYPE_TRAVEL_TRADE => Exhibitor::CATEGORY_TRAVEL,
            Inquiry::TYPE_MEDIA        => Exhibitor::CATEGORY_MSME,
            default                    => Exhibitor::CATEGORY_MSME,
        };

        $exhibitor = new Exhibitor();
        $exhibitor->setOrganization($inquiry->getOrganization() ?? $inquiry->getName());
        $exhibitor->setContactName($inquiry->getName());
        $exhibitor->setContactEmail($inquiry->getEmail());
        $exhibitor->setPhone($inquiry->getPhone());
        $exhibitor->setCategory($category);
        $exhibitor->setPackageTier(Exhibitor::TIER_EXHIBITOR);
        $exhibitor->setStatus(Exhibitor::STATUS_PROSPECT);
        $exhibitor->setPaymentStatus(Exhibitor::PAYMENT_UNPAID);
        $exhibitor->setDescription($inquiry->getMessage());
        $exhibitor->setSourceInquiry($inquiry);
        $exhibitor->setYear(2026);

        // Mark inquiry won
        $inquiry->setStatus(Inquiry::STATUS_WON);

        $this->em->persist($exhibitor);
        $this->em->flush();

        return $exhibitor;
    }
}