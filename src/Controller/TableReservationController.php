<?php
// src/Controller/Api/TableReservationController.php
namespace App\Controller;

use App\Entity\TableReservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
#[Route('/table_reservations')]
class TableReservationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator
    ) {}

    #[Route('/check_availability', name: 'table_reservations_check_availability', methods: ['POST'])]
    public function checkAvailability(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Create today's date for comparison
        $today = new \DateTime('today');
        
        // Validate input
        $constraints = new Assert\Collection([
            'reservationDate' => [
                new Assert\NotBlank(),
                new Assert\Date(),
                new Assert\Callback(function($value, $context) use ($today) {
                    if (!$value) return;
                    
                    $reservationDate = new \DateTime($value);
                    if ($reservationDate < $today) {
                        $context->buildViolation('Reservation date must be today or in the future')
                            ->addViolation();
                    }
                })
            ],
            'startTime' => [
                new Assert\NotBlank(),
                new Assert\Regex([
                    'pattern' => '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
                    'message' => 'Time must be in HH:MM format (24-hour)'
                ])
            ],
            'durationMinutes' => [
                new Assert\Optional([
                    new Assert\Type('integer'),
                    new Assert\Range(min: 30, max: 240)
                ])
            ],
            'guestCount' => [
                new Assert\Optional([
                    new Assert\Type('integer'),
                    new Assert\Range(min: 1, max: 10)
                ])
            ]
        ]);

        $violations = $this->validator->validate($data, $constraints);
        
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }
            return $this->json(['errors' => $errors], 400);
        }

        $reservationDate = new \DateTime($data['reservationDate']);
        $startTimeString = $data['startTime'];
        
        // Ensure time is in correct format (HH:MM)
        if (strlen($startTimeString) === 4) {
            $startTimeString = '0' . $startTimeString; // Convert "9:00" to "09:00"
        }
        
        $startTime = \DateTime::createFromFormat('H:i', $startTimeString);
        $durationMinutes = $data['durationMinutes'] ?? 90;
        $guestCount = $data['guestCount'] ?? 2;
        
        // Calculate end time
        $endTime = clone $startTime;
        $endTime->modify("+{$durationMinutes} minutes");

        // Check availability
        $availableTables = $this->checkTableAvailability($reservationDate, $startTime, $endTime);
        
        $response = [
            'availableTables' => $availableTables,
            'isAvailable' => $availableTables > 0,
            'requested' => [
                'date' => $reservationDate->format('Y-m-d'),
                'time' => $startTime->format('H:i'),
                'durationMinutes' => $durationMinutes,
                'guestCount' => $guestCount
            ],
            'maxTables' => TableReservation::MAX_TABLES
        ];

        // Suggest alternatives if no tables available
        if ($availableTables === 0) {
            $response['alternativeSlots'] = $this->getAlternativeTimeSlots(
                $reservationDate, 
                $startTime, 
                $durationMinutes
            );
        }

        return $this->json($response);
    }

    // ... rest of the methods remain the same ...
    
    private function checkTableAvailability(\DateTimeInterface $date, \DateTimeInterface $startTime, \DateTimeInterface $endTime): int
    {
        $query = $this->entityManager->getRepository(TableReservation::class)
            ->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.reservationDate = :date')
            ->andWhere('r.status != :cancelled')
            ->andWhere('(r.startTime < :endTime AND 
                       DATE_ADD(r.startTime, r.durationMinutes, \'MINUTE\') > :startTime)')
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('startTime', $startTime->format('H:i:s'))
            ->setParameter('endTime', $endTime->format('H:i:s'))
            ->setParameter('cancelled', TableReservation::STATUS_CANCELLED)
            ->getQuery();

        $existingReservations = (int) $query->getSingleScalarResult();
        
        return TableReservation::MAX_TABLES - $existingReservations;
    }

    private function getAlternativeTimeSlots(\DateTimeInterface $date, \DateTimeInterface $requestedTime, int $duration): array
    {
        $alternatives = [];
        
        // Define time slots to check (in minutes offset)
        $timeOffsets = [-60, -45, -30, -15, 15, 30, 45, 60, 75, 90, 105, 120];
        
        foreach ($timeOffsets as $minutes) {
            $alternativeTime = clone $requestedTime;
            $alternativeTime->modify("{$minutes} minutes");
            
            // Don't suggest times before opening (9 AM) or after closing (11 PM)
            $hour = (int)$alternativeTime->format('H');
            if ($hour < 9 || $hour > 23) {
                continue;
            }
            
            $endTime = clone $alternativeTime;
            $endTime->modify("+{$duration} minutes");
            
            $availableTables = $this->checkTableAvailability($date, $alternativeTime, $endTime);
            
            if ($availableTables > 0) {
                $alternatives[] = [
                    'time' => $alternativeTime->format('H:i'),
                    'availableTables' => $availableTables,
                    'offsetMinutes' => $minutes,
                    'displayTime' => $alternativeTime->format('g:i A') // Format for display
                ];
            }
        }
        
        // Sort by time (chronological)
        usort($alternatives, fn($a, $b) => $a['offsetMinutes'] <=> $b['offsetMinutes']);
        
        return $alternatives;
    }
}