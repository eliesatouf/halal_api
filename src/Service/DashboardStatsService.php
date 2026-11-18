<?php
// src/Service/DashboardStatsService.php

namespace App\Service;

use App\Entity\Inquiry;
use App\Entity\Note;
use Doctrine\ORM\EntityManagerInterface;

class DashboardStatsService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function getStats(): array
    {
        return [
            'inquiries' => $this->inquiryStats(),
            'exhibitors' => $this->exhibitorStats(),
            'recentActivity' => $this->recentActivity(),
            'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }

    private function inquiryStats(): array
    {
        $conn = $this->em->getConnection();

        $total = (int) $conn->fetchOne('SELECT COUNT(*) FROM inquiry');

        $byStatus = array_map('intval', $conn->fetchAllKeyValue(
            'SELECT status, COUNT(*) FROM inquiry GROUP BY status'
        ));

        $byType = array_map('intval', $conn->fetchAllKeyValue(
            'SELECT type, COUNT(*) FROM inquiry GROUP BY type'
        ));

        $last24h = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM inquiry WHERE created_on >= :t",
            ['t' => (new \DateTime('-24 hours'))->format('Y-m-d H:i:s')]
        );

        $awaitingContact = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM inquiry WHERE status = 'new' AND created_on < :t",
            ['t' => (new \DateTime('-24 hours'))->format('Y-m-d H:i:s')]
        );

        return [
            'total' => $total,
            'last24h' => $last24h,
            'awaitingContact' => $awaitingContact,
            'byStatus' => [
                'new' => $byStatus['new'] ?? 0,
                'contacted' => $byStatus['contacted'] ?? 0,
                'qualified' => $byStatus['qualified'] ?? 0,
                'won' => $byStatus['won'] ?? 0,
                'lost' => $byStatus['lost'] ?? 0,
            ],
            'byType' => $byType,
        ];
    }

    private function exhibitorStats(): array
    {
        $conn = $this->em->getConnection();

        $total = (int) $conn->fetchOne('SELECT COUNT(*) FROM exhibitor');

        $byStatus = array_map('intval', $conn->fetchAllKeyValue(
            'SELECT status, COUNT(*) FROM exhibitor GROUP BY status'
        ));
        $byTier = array_map('intval', $conn->fetchAllKeyValue(
            'SELECT package_tier, COUNT(*) FROM exhibitor GROUP BY package_tier'
        ));
        $byPayment = array_map('intval', $conn->fetchAllKeyValue(
            'SELECT payment_status, COUNT(*) FROM exhibitor GROUP BY payment_status'
        ));

        return [
            'total' => $total,
            'byStatus' => [
                'prospect' => $byStatus['prospect'] ?? 0,
                'confirmed' => $byStatus['confirmed'] ?? 0,
                'paid' => $byStatus['paid'] ?? 0,
                'cancelled' => $byStatus['cancelled'] ?? 0,
            ],
            'byTier' => [
                'exhibitor' => $byTier['exhibitor'] ?? 0,
                'partner' => $byTier['partner'] ?? 0,
                'sponsor' => $byTier['sponsor'] ?? 0,
            ],
            'byPayment' => [
                'unpaid' => $byPayment['unpaid'] ?? 0,
                'partial' => $byPayment['partial'] ?? 0,
                'paid' => $byPayment['paid'] ?? 0,
                'waived' => $byPayment['waived'] ?? 0,
            ],
            'confirmedCount' => ($byStatus['confirmed'] ?? 0) + ($byStatus['paid'] ?? 0),
            'outstandingPayments' => ($byPayment['unpaid'] ?? 0) + ($byPayment['partial'] ?? 0),
        ];
    }

    private function recentActivity(): array
    {
        $em = $this->em;

        $inquiries = $em->createQueryBuilder()
            ->select('i')
            ->from(Inquiry::class, 'i')
            ->orderBy('i.createdOn', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $notes = $em->createQueryBuilder()
            ->select('n')
            ->from(Note::class, 'n')
            ->orderBy('n.createdOn', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $events = [];

        foreach ($inquiries as $i) {
            $events[] = [
                'type' => 'inquiry',
                'at' => $i->getCreatedOn()?->format(\DateTimeInterface::ATOM),
                'title' => $i->getName() . ' submitted an inquiry',
                'subtitle' => ucfirst(str_replace('_', ' ', $i->getType())) . ' · ' . ($i->getOrganization() ?? 'No org'),
                'href' => '/inquiries/' . $i->getId(),
                'badge' => $i->getStatus(),
            ];
        }

        foreach ($notes as $n) {
            $author = $n->getAuthor();
            $authorName = $author
                ? (trim(($author->getGivenName() ?? '') . ' ' . ($author->getFamilyName() ?? '')) ?: $author->getEmail())
                : 'Someone';

            $href = match ($n->getEntityType()) {
                'inquiry' => '/inquiries/' . $n->getEntityId(),
                'exhibitor' => '/exhibitors/' . $n->getEntityId(),
                default => '#',
            };

            $events[] = [
                'type' => 'note',
                'at' => $n->getCreatedOn()?->format(\DateTimeInterface::ATOM),
                'title' => $authorName . ' added a note',
                'subtitle' => mb_strimwidth($n->getBody() ?? '', 0, 80, '…'),
                'href' => $href,
                'badge' => $n->getEntityType(),
            ];
        }

        usort($events, fn($a, $b) => strcmp($b['at'] ?? '', $a['at'] ?? ''));
        return array_slice($events, 0, 15);
    }
}