<?php
// src/State/TableReservationProcessor.php
namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\TableReservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class TableReservationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof TableReservation && $operation->getName() === 'post') {
            // Validate table availability
            $this->validateTableAvailability($data);
            
            // Check for duplicate reservations
            $this->checkForDuplicates($data);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function validateTableAvailability(TableReservation $reservation): void
    {
        $availableTables = $this->getAvailableTablesCount(
            $reservation->getReservationDate(),
            $reservation->getStartTime(),
            $reservation->getEndTime()
        );

        if ($availableTables <= 0) {
            throw new BadRequestHttpException('No tables available for the selected time slot. Please choose a different time.');
        }
    }

    private function checkForDuplicates(TableReservation $reservation): void
    {
        $existing = $this->entityManager->getRepository(TableReservation::class)
            ->createQueryBuilder('r')
            ->where('r.customerEmail = :email')
            ->andWhere('r.reservationDate = :date')
            ->andWhere('TIME(r.startTime) = TIME(:startTime)')
            ->andWhere('r.status != :cancelled')
            ->setParameter('email', $reservation->getCustomerEmail())
            ->setParameter('date', $reservation->getReservationDate())
            ->setParameter('startTime', $reservation->getStartTime())
            ->setParameter('cancelled', TableReservation::STATUS_CANCELLED)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing) {
            throw new UnprocessableEntityHttpException('You already have a reservation for this time slot.');
        }
    }

    private function getAvailableTablesCount(\DateTimeInterface $date, \DateTimeInterface $startTime, \DateTimeInterface $endTime): int
    {
        $query = $this->entityManager->getRepository(TableReservation::class)
            ->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.reservationDate = :date')
            ->andWhere('r.status != :cancelled')
            ->andWhere('(r.startTime < :endTime AND 
                       DATE_ADD(r.startTime, r.durationMinutes, \'MINUTE\') > :startTime)')
            ->setParameter('date', $date)
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime)
            ->setParameter('cancelled', TableReservation::STATUS_CANCELLED)
            ->getQuery();

        $existingReservations = (int) $query->getSingleScalarResult();
        
        return TableReservation::MAX_TABLES - $existingReservations;
    }
}