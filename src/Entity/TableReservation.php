<?php
// src/Entity/TableReservation.php
namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Serializer\Filter\PropertyFilter;

#[ORM\Entity]
#[ORM\Table(name: 'table_reservations')]
#[ApiResource(
    mercure: [
        'topics' => [
            '/api/orders/{id}',  // Individual order updates
            '/sandys_orders'            // Custom topic for ALL orders
        ]
    ],
    operations: [
        new Get(),
        new GetCollection(),
        new Post(
            validationContext: ['groups' => ['reservation:create']],
            mercure: [
                    'topics' => [
                        '/api/orders/{id}',
                        '/sandys_orders'  // Publish to custom topic on creation
                    ]
                ]
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN') or object.customerEmail == user.getUserIdentifier()",
            denormalizationContext: ['groups' => ['reservation:update']]
        )
    ],
    normalizationContext: ['groups' => ['reservation:read']],
    denormalizationContext: ['groups' => ['reservation:create']]
)]
#[ApiFilter(DateFilter::class, properties: ['reservationDate', 'createdAt'])]
#[ApiFilter(SearchFilter::class, properties: [
    'customerName' => 'partial',
    'customerEmail' => 'exact',
    'status' => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: ['reservationDate', 'startTime', 'createdAt'])]
class TableReservation
{
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PENDING = 'pending';
    const STATUS_CANCELLED = 'cancelled';
    
    const SITTING_PERIOD_LUNCH = 'lunch';
    const SITTING_PERIOD_DINNER = 'dinner';
    const SITTING_PERIOD_BREAKFAST = 'breakfast';
    const SITTING_PERIOD_BRUNCH = 'brunch';
    
    const MAX_TABLES = 10;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['reservation:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(groups: ['reservation:create'])]
    #[Assert\Length(min: 2, max: 100, groups: ['reservation:create'])]
    #[Groups(['reservation:create', 'reservation:read', 'reservation:update'])]
    private string $customerName;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(groups: ['reservation:create'])]
    #[Assert\Email(groups: ['reservation:create'])]
    #[Assert\Length(max: 180, groups: ['reservation:create'])]
    #[Groups(['reservation:create', 'reservation:read'])]
    private string $customerEmail;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(groups: ['reservation:create'])]
    #[Assert\Length(max: 20, groups: ['reservation:create'])]
    #[Groups(['reservation:create', 'reservation:read'])]
    private string $customerPhone;

    #[ORM\Column]
    #[Assert\NotBlank(groups: ['reservation:create'])]
    #[Assert\Range(min: 1, max: 20, groups: ['reservation:create'])]
    #[Groups(['reservation:create', 'reservation:read'])]
    private int $guestCount;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank(groups: ['reservation:create'])]
    #[Assert\GreaterThanOrEqual('today', groups: ['reservation:create'])]
    #[Groups(['reservation:create', 'reservation:read'])]
    private \DateTimeInterface $reservationDate;

    #[ORM\Column(type: 'time')]
    #[Assert\NotBlank(groups: ['reservation:create'])]
    #[Groups(['reservation:create', 'reservation:read'])]
    private \DateTimeInterface $startTime;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(groups: ['reservation:create'])]
    #[Assert\Choice([
        self::SITTING_PERIOD_BREAKFAST,
        self::SITTING_PERIOD_LUNCH,
        self::SITTING_PERIOD_BRUNCH,
        self::SITTING_PERIOD_DINNER
    ], groups: ['reservation:create'])]
    #[Groups(['reservation:create', 'reservation:read'])]
    private string $sittingPeriod;

    #[ORM\Column]
    #[Assert\NotBlank(groups: ['reservation:create'])]
    #[Assert\Range(min: 30, max: 240, groups: ['reservation:create'])]
    #[Groups(['reservation:create', 'reservation:read'])]
    private int $durationMinutes = 90;

    #[ORM\Column(length: 20)]
    #[Groups(['reservation:create','reservation:read', 'reservation:update'])]
    private string $status;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['reservation:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['reservation:create', 'reservation:read', 'reservation:update'])]
    private ?string $specialRequests = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): self
    {
        $this->customerName = $customerName;
        return $this;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(string $customerEmail): self
    {
        $this->customerEmail = $customerEmail;
        return $this;
    }

    public function getCustomerPhone(): string
    {
        return $this->customerPhone;
    }

    public function setCustomerPhone(string $customerPhone): self
    {
        $this->customerPhone = $customerPhone;
        return $this;
    }

    public function getGuestCount(): int
    {
        return $this->guestCount;
    }

    public function setGuestCount(int $guestCount): self
    {
        $this->guestCount = $guestCount;
        return $this;
    }

    public function getReservationDate(): \DateTimeInterface
    {
        return $this->reservationDate;
    }

    public function setReservationDate(\DateTimeInterface $reservationDate): self
    {
        $this->reservationDate = $reservationDate;
        return $this;
    }

    public function getStartTime(): \DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getSittingPeriod(): string
    {
        return $this->sittingPeriod;
    }

    public function setSittingPeriod(string $sittingPeriod): self
    {
        $this->sittingPeriod = $sittingPeriod;
        return $this;
    }

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): self
    {
        $this->durationMinutes = $durationMinutes;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSpecialRequests(): ?string
    {
        return $this->specialRequests;
    }

    public function setSpecialRequests(?string $specialRequests): self
    {
        $this->specialRequests = $specialRequests;
        return $this;
    }

    public function getEndTime(): \DateTimeInterface
    {
        $endTime = clone $this->startTime;
        return $endTime->modify("+{$this->durationMinutes} minutes");
    }
}