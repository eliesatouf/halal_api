<?php
// src/Entity/Exhibitor.php

namespace App\Entity;

use App\Repository\ExhibitorRepository;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['exhibitor:list', 'user:list']],
            security: 'is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'
        ),
        new Get(
            normalizationContext: ['groups' => ['exhibitor:read', 'user:list']],
            security: 'is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'
        ),
        new Post(
            denormalizationContext: ['groups' => ['exhibitor:create']],
            normalizationContext: ['groups' => ['exhibitor:read']],
            validationContext: ['groups' => ['exhibitor:create']],
            security: 'is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'
        ),
        new Patch(
            denormalizationContext: ['groups' => ['exhibitor:update']],
            normalizationContext: ['groups' => ['exhibitor:read', 'user:list']],
            validationContext: ['groups' => ['exhibitor:update']],
            security: 'is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'
        ),
    ]
)]

#[ApiFilter(SearchFilter::class, properties: [
    'organization' => 'partial',
    'contactEmail' => 'partial',
    'contactName'  => 'partial',
    'category'     => 'exact',
    'packageTier'  => 'exact',
    'status'       => 'exact',
    'paymentStatus'=> 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['createdOn'])]

#[ORM\Entity(repositoryClass: ExhibitorRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['status'], name: 'idx_exhibitor_status')]
#[ORM\Index(columns: ['package_tier'], name: 'idx_exhibitor_tier')]
#[ORM\Index(columns: ['category'], name: 'idx_exhibitor_category')]
class Exhibitor
{
    public const CATEGORY_EMBASSY    = 'embassy';
    public const CATEGORY_HOTEL      = 'hotel';
    public const CATEGORY_AIRLINE    = 'airline';
    public const CATEGORY_LGU        = 'lgu';
    public const CATEGORY_MSME       = 'msme';
    public const CATEGORY_CERTIFIER  = 'certifier';
    public const CATEGORY_TRAVEL     = 'travel';

    public const TIER_EXHIBITOR = 'exhibitor';
    public const TIER_PARTNER   = 'partner';
    public const TIER_SPONSOR   = 'sponsor';

    public const STATUS_PROSPECT  = 'prospect';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PAID      = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_UNPAID  = 'unpaid';
    public const PAYMENT_PARTIAL = 'partial';
    public const PAYMENT_PAID    = 'paid';
    public const PAYMENT_WAIVED  = 'waived';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['exhibitor:read', 'exhibitor:list'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['exhibitor:create', 'exhibitor:read', 'exhibitor:list', 'exhibitor:update'])]
    #[Assert\NotBlank(groups: ['exhibitor:create'])]
    #[Assert\Length(max: 255)]
    private ?string $organization = null;

    #[ORM\Column(length: 255)]
    #[Groups(['exhibitor:create', 'exhibitor:read', 'exhibitor:list', 'exhibitor:update'])]
    #[Assert\NotBlank(groups: ['exhibitor:create'])]
    #[Assert\Length(max: 255)]
    private ?string $contactName = null;

    #[ORM\Column(length: 255)]
    #[Groups(['exhibitor:create', 'exhibitor:read', 'exhibitor:list', 'exhibitor:update'])]
    #[Assert\NotBlank(groups: ['exhibitor:create'])]
    #[Assert\Email(mode: 'strict')]
    private ?string $contactEmail = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['exhibitor:create', 'exhibitor:read', 'exhibitor:update'])]
    private ?string $phone = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['exhibitor:create', 'exhibitor:read', 'exhibitor:update'])]
    private ?string $website = null;

    #[ORM\Column(length: 30)]
    #[Groups(['exhibitor:create', 'exhibitor:read', 'exhibitor:list', 'exhibitor:update'])]
    #[Assert\NotBlank(groups: ['exhibitor:create'])]
    #[Assert\Choice(choices: [
        self::CATEGORY_EMBASSY, self::CATEGORY_HOTEL, self::CATEGORY_AIRLINE,
        self::CATEGORY_LGU, self::CATEGORY_MSME, self::CATEGORY_CERTIFIER,
        self::CATEGORY_TRAVEL,
    ])]
    private ?string $category = null;

    #[ORM\Column(length: 30)]
    #[Groups(['exhibitor:create', 'exhibitor:read', 'exhibitor:list', 'exhibitor:update'])]
    #[Assert\Choice(choices: [self::TIER_EXHIBITOR, self::TIER_PARTNER, self::TIER_SPONSOR])]
    private string $packageTier = self::TIER_EXHIBITOR;

    #[ORM\Column(length: 30)]
    #[Groups(['exhibitor:read', 'exhibitor:list', 'exhibitor:update'])]
    #[Assert\Choice(choices: [
        self::STATUS_PROSPECT, self::STATUS_CONFIRMED,
        self::STATUS_PAID, self::STATUS_CANCELLED,
    ])]
    private string $status = self::STATUS_PROSPECT;

    #[ORM\Column(length: 30)]
    #[Groups(['exhibitor:read', 'exhibitor:list', 'exhibitor:update'])]
    #[Assert\Choice(choices: [
        self::PAYMENT_UNPAID, self::PAYMENT_PARTIAL,
        self::PAYMENT_PAID, self::PAYMENT_WAIVED,
    ])]
    private string $paymentStatus = self::PAYMENT_UNPAID;

    #[ORM\Column(length: 30, nullable: true)]
    #[Groups(['exhibitor:read', 'exhibitor:list', 'exhibitor:update'])]
    private ?string $boothNumber = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['exhibitor:read', 'exhibitor:update'])]
    private ?\DateTime $contractSignedOn = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['exhibitor:create', 'exhibitor:read', 'exhibitor:update'])]
    private ?string $logoUrl = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['exhibitor:create', 'exhibitor:read', 'exhibitor:update'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['exhibitor:read', 'exhibitor:list'])]
    private int $year = 2026;

    #[ORM\ManyToOne(targetEntity: Inquiry::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['exhibitor:read'])]
    private ?Inquiry $sourceInquiry = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['exhibitor:read', 'exhibitor:list'])]
    private ?User $assignedTo = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['exhibitor:read', 'exhibitor:list'])]
    private ?\DateTime $createdOn = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['exhibitor:read'])]
    private ?\DateTime $updatedOn = null;

    #[ORM\PrePersist]
    public function setCreatedOnValue(): void
    {
        if ($this->createdOn === null) {
            $this->createdOn = new \DateTime();
        }
        $this->updatedOn = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedOn = new \DateTime();
    }

    // --- Getters and setters ---

    public function getId(): ?int { return $this->id; }

    public function getOrganization(): ?string { return $this->organization; }
    public function setOrganization(string $organization): static { $this->organization = $organization; return $this; }

    public function getContactName(): ?string { return $this->contactName; }
    public function setContactName(string $contactName): static { $this->contactName = $contactName; return $this; }

    public function getContactEmail(): ?string { return $this->contactEmail; }
    public function setContactEmail(string $contactEmail): static { $this->contactEmail = $contactEmail; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): static { $this->phone = $phone; return $this; }

    public function getWebsite(): ?string { return $this->website; }
    public function setWebsite(?string $website): static { $this->website = $website; return $this; }

    public function getCategory(): ?string { return $this->category; }
    public function setCategory(string $category): static { $this->category = $category; return $this; }

    public function getPackageTier(): string { return $this->packageTier; }
    public function setPackageTier(string $packageTier): static { $this->packageTier = $packageTier; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getPaymentStatus(): string { return $this->paymentStatus; }
    public function setPaymentStatus(string $paymentStatus): static { $this->paymentStatus = $paymentStatus; return $this; }

    public function getBoothNumber(): ?string { return $this->boothNumber; }
    public function setBoothNumber(?string $boothNumber): static { $this->boothNumber = $boothNumber; return $this; }

    public function getContractSignedOn(): ?\DateTime { return $this->contractSignedOn; }
    public function setContractSignedOn(?\DateTime $contractSignedOn): static { $this->contractSignedOn = $contractSignedOn; return $this; }

    public function getLogoUrl(): ?string { return $this->logoUrl; }
    public function setLogoUrl(?string $logoUrl): static { $this->logoUrl = $logoUrl; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getYear(): int { return $this->year; }
    public function setYear(int $year): static { $this->year = $year; return $this; }

    public function getSourceInquiry(): ?Inquiry { return $this->sourceInquiry; }
    public function setSourceInquiry(?Inquiry $sourceInquiry): static { $this->sourceInquiry = $sourceInquiry; return $this; }

    public function getAssignedTo(): ?User { return $this->assignedTo; }
    public function setAssignedTo(?User $assignedTo): static { $this->assignedTo = $assignedTo; return $this; }

    public function getCreatedOn(): ?\DateTime { return $this->createdOn; }
    public function setCreatedOn(?\DateTime $createdOn): static { $this->createdOn = $createdOn; return $this; }

    public function getUpdatedOn(): ?\DateTime { return $this->updatedOn; }
    public function setUpdatedOn(?\DateTime $updatedOn): static { $this->updatedOn = $updatedOn; return $this; }
}