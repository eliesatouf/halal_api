<?php
// src/Entity/Inquiry.php

namespace App\Entity;

use App\Repository\InquiryRepository;
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
        // Public: form submission — no auth
        new Post(
            uriTemplate: '/inquiries',
            denormalizationContext: ['groups' => ['inquiry:create']],
            normalizationContext: ['groups' => ['inquiry:read']],
            security: 'true',  // public — no auth required
            validationContext: ['groups' => ['inquiry:create']],
        ),
        // Admin list — filterable
        new GetCollection(
            normalizationContext: ['groups' => ['inquiry:list', 'user:list']],
            security: 'is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'
        ),
        // Admin detail
        new Get(
            normalizationContext: ['groups' => ['inquiry:read', 'user:list']],
            security: 'is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'
        ),
        // Admin update — only status + assignment
        new Patch(
            denormalizationContext: ['groups' => ['inquiry:update']],
            normalizationContext: ['groups' => ['inquiry:read', 'user:list']],
            security: 'is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")',
            validationContext: ['groups' => ['inquiry:update']]
        ),
    ]
)]

#[ApiFilter(SearchFilter::class, properties: [
    'status' => 'exact',
    'type' => 'exact',
    'email' => 'partial',
    'name' => 'partial',
    'organization' => 'partial',
])]
#[ApiFilter(DateFilter::class, properties: ['createdOn'])]

#[ORM\Entity(repositoryClass: InquiryRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['status'], name: 'idx_inquiry_status')]
#[ORM\Index(columns: ['type'], name: 'idx_inquiry_type')]
#[ORM\Index(columns: ['created_on'], name: 'idx_inquiry_created_on')]
class Inquiry
{
    public const TYPE_EXHIBITOR    = 'exhibitor';
    public const TYPE_PARTNER      = 'partner';
    public const TYPE_MEDIA        = 'media';
    public const TYPE_TRAVEL_TRADE = 'travel_trade';
    public const TYPE_GENERAL      = 'general';

    public const STATUS_NEW       = 'new';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_QUALIFIED = 'qualified';
    public const STATUS_WON       = 'won';
    public const STATUS_LOST      = 'lost';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['inquiry:read', 'inquiry:list'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['inquiry:create', 'inquiry:read', 'inquiry:list'])]
    #[Assert\NotBlank(groups: ['inquiry:create'])]
    #[Assert\Length(max: 255, groups: ['inquiry:create'])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Groups(['inquiry:create', 'inquiry:read', 'inquiry:list'])]
    #[Assert\NotBlank(groups: ['inquiry:create'])]
    #[Assert\Email(mode: 'strict', groups: ['inquiry:create'])]
    #[Assert\Length(max: 255, groups: ['inquiry:create'])]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['inquiry:create', 'inquiry:read', 'inquiry:list'])]
    #[Assert\Length(max: 255, groups: ['inquiry:create'])]
    private ?string $organization = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['inquiry:create', 'inquiry:read'])]
    #[Assert\Length(max: 50, groups: ['inquiry:create'])]
    private ?string $phone = null;

    #[ORM\Column(length: 30)]
    #[Groups(['inquiry:create', 'inquiry:read', 'inquiry:list'])]
    #[Assert\NotBlank(groups: ['inquiry:create'])]
    #[Assert\Choice(choices: [
        self::TYPE_EXHIBITOR, self::TYPE_PARTNER, self::TYPE_MEDIA,
        self::TYPE_TRAVEL_TRADE, self::TYPE_GENERAL,
    ], groups: ['inquiry:create'])]
    private ?string $type = self::TYPE_GENERAL;

    #[ORM\Column(type: 'text')]
    #[Groups(['inquiry:create', 'inquiry:read'])]
    #[Assert\NotBlank(groups: ['inquiry:create'])]
    #[Assert\Length(min: 10, max: 5000, groups: ['inquiry:create'])]
    private ?string $message = null;

    #[ORM\Column]
    #[Groups(['inquiry:create', 'inquiry:read'])]
    #[Assert\IsTrue(message: 'You must agree to be contacted.', groups: ['inquiry:create'])]
    private ?bool $consent = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['inquiry:create', 'inquiry:read'])]
    private ?string $sourcePath = null;

    #[ORM\Column(length: 30)]
    #[Groups(['inquiry:read', 'inquiry:list', 'inquiry:update'])]
    #[Assert\Choice(choices: [
        self::STATUS_NEW, self::STATUS_CONTACTED, self::STATUS_QUALIFIED,
        self::STATUS_WON, self::STATUS_LOST,
    ], groups: ['inquiry:update'])]
    private string $status = self::STATUS_NEW;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['inquiry:read', 'inquiry:list',   ])]
    private ?User $assignedTo = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['inquiry:read', 'inquiry:list'])]
    private ?\DateTime $createdOn = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['inquiry:read'])]
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

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getOrganization(): ?string { return $this->organization; }
    public function setOrganization(?string $organization): static { $this->organization = $organization; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): static { $this->phone = $phone; return $this; }

    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getMessage(): ?string { return $this->message; }
    public function setMessage(string $message): static { $this->message = $message; return $this; }

    public function isConsent(): ?bool { return $this->consent; }
    public function setConsent(bool $consent): static { $this->consent = $consent; return $this; }

    public function getSourcePath(): ?string { return $this->sourcePath; }
    public function setSourcePath(?string $sourcePath): static { $this->sourcePath = $sourcePath; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getAssignedTo(): ?User { return $this->assignedTo; }
    public function setAssignedTo(?User $assignedTo): static { $this->assignedTo = $assignedTo; return $this; }

    public function getCreatedOn(): ?\DateTime { return $this->createdOn; }
    public function setCreatedOn(?\DateTime $createdOn): static { $this->createdOn = $createdOn; return $this; }

    public function getUpdatedOn(): ?\DateTime { return $this->updatedOn; }
    public function setUpdatedOn(?\DateTime $updatedOn): static { $this->updatedOn = $updatedOn; return $this; }
}