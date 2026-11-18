<?php
// src/Controller/ExportController.php

namespace App\Controller;

use App\Repository\ExhibitorRepository;
use App\Repository\InquiryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/exports')]
#[IsGranted('ROLE_STAFF')]
class ExportController extends AbstractController
{
    #[Route('/inquiries.csv', name: 'export_inquiries_csv', methods: ['GET'])]
    public function inquiriesCsv(Request $request, InquiryRepository $inquiries): StreamedResponse
    {
        $status = $request->query->get('status');
        $type = $request->query->get('type');

        $qb = $inquiries->createQueryBuilder('i')
            ->orderBy('i.createdOn', 'DESC');

        if ($status) $qb->andWhere('i.status = :s')->setParameter('s', $status);
        if ($type)   $qb->andWhere('i.type = :t')->setParameter('t', $type);

        $rows = $qb->getQuery()->getResult();

        $headers = [
            'ID', 'Date', 'Name', 'Email', 'Organization', 'Phone',
            'Type', 'Status', 'Assigned To', 'Source Path', 'Message',
        ];

        return $this->streamCsv('inquiries-' . date('Y-m-d') . '.csv', $headers, $rows, function ($i) {
            return [
                $i->getId(),
                $i->getCreatedOn()?->format('Y-m-d H:i'),
                $i->getName(),
                $i->getEmail(),
                $i->getOrganization() ?? '',
                $i->getPhone() ?? '',
                $i->getType(),
                $i->getStatus(),
                $i->getAssignedTo()
                    ? (trim(($i->getAssignedTo()->getGivenName() ?? '') . ' ' . ($i->getAssignedTo()->getFamilyName() ?? '')) ?: $i->getAssignedTo()->getEmail())
                    : '',
                $i->getSourcePath() ?? '',
                $i->getMessage(),
            ];
        });
    }

    #[Route('/exhibitors.csv', name: 'export_exhibitors_csv', methods: ['GET'])]
    public function exhibitorsCsv(Request $request, ExhibitorRepository $exhibitors): StreamedResponse
    {
        $status = $request->query->get('status');
        $category = $request->query->get('category');
        $packageTier = $request->query->get('packageTier');
        $paymentStatus = $request->query->get('paymentStatus');

        $qb = $exhibitors->createQueryBuilder('e')
            ->orderBy('e.createdOn', 'DESC');

        if ($status)        $qb->andWhere('e.status = :s')->setParameter('s', $status);
        if ($category)      $qb->andWhere('e.category = :c')->setParameter('c', $category);
        if ($packageTier)   $qb->andWhere('e.packageTier = :t')->setParameter('t', $packageTier);
        if ($paymentStatus) $qb->andWhere('e.paymentStatus = :p')->setParameter('p', $paymentStatus);

        $rows = $qb->getQuery()->getResult();

        $headers = [
            'ID', 'Created', 'Organization', 'Contact Name', 'Contact Email', 'Phone',
            'Website', 'Category', 'Tier', 'Status', 'Payment', 'Booth',
            'Contract Signed', 'Assigned To', 'Source Inquiry ID', 'Description',
        ];

        return $this->streamCsv('exhibitors-' . date('Y-m-d') . '.csv', $headers, $rows, function ($e) {
            return [
                $e->getId(),
                $e->getCreatedOn()?->format('Y-m-d H:i'),
                $e->getOrganization(),
                $e->getContactName(),
                $e->getContactEmail(),
                $e->getPhone() ?? '',
                $e->getWebsite() ?? '',
                $e->getCategory(),
                $e->getPackageTier(),
                $e->getStatus(),
                $e->getPaymentStatus(),
                $e->getBoothNumber() ?? '',
                $e->getContractSignedOn()?->format('Y-m-d') ?? '',
                $e->getAssignedTo()
                    ? (trim(($e->getAssignedTo()->getGivenName() ?? '') . ' ' . ($e->getAssignedTo()->getFamilyName() ?? '')) ?: $e->getAssignedTo()->getEmail())
                    : '',
                $e->getSourceInquiry()?->getId() ?? '',
                $e->getDescription() ?? '',
            ];
        });
    }

    /**
     * Stream CSV line-by-line to avoid buffering the whole file in memory.
     * Important when exports grow to thousands of rows.
     */
    private function streamCsv(string $filename, array $headers, array $rows, callable $rowMapper): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rows, $rowMapper) {
            $out = fopen('php://output', 'w');

            // BOM for Excel compatibility with UTF-8
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, $rowMapper($row));
            }

            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="' . $filename . '"'
        );
        // Prevent caching
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');

        return $response;
    }
}