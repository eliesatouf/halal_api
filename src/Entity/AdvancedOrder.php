<?php

namespace App\Entity;

use App\Repository\AdvancedOrderRepository;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;

#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(
            validationContext: ['groups' => ['advancedOrder:write']],
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN') or object.customerEmail == user.getUserIdentifier()",
            denormalizationContext: ['groups' => ['advancedOrder:write']]
        )
    ],
    normalizationContext: ['groups' => ['advancedOrder:read']],
    denormalizationContext: ['groups' => ['advancedOrder:write']]
)]

#[ApiFilter(DateFilter::class, properties: ['reservationDate', 'createdAt'])]
#[ApiFilter(SearchFilter::class, properties: [
    'customerName' => 'partial',
    'customerEmail' => 'exact',
    'status' => 'exact'
])]

#[ApiFilter(OrderFilter::class, properties: ['createdAt'])]

#[ORM\Entity(repositoryClass: AdvancedOrderRepository::class)]
class AdvancedOrder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['advancedOrder:read', 'order:read'])]
    private ?int $id = null;

    #[Groups(['advancedOrder:read','advancedOrder:write', 'order:read','order:write'])]
    #[ORM\Column(nullable: true)]
    private ?\DateTime $scheduledFor = null;

    #[Groups(['advancedOrder:read','advancedOrder:write', 'order:read','order:write'])]
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[Groups(['advancedOrder:read','advancedOrder:write', 'order:read','order:write'])]
    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $notes = null;

    #[Groups(['advancedOrder:read','advancedOrder:write', 'order:read','order:write'])]
    #[ORM\Column(nullable: true)]
    private ?int $advanceNoticeHours = null;

    #[Groups(['advancedOrder:read','advancedOrder:write', 'order:read','order:write'])]
    #[ORM\Column(nullable: true)]
    private ?int $advanceNoticeDays = null;


    #[ORM\OneToOne(inversedBy: 'advancedOrder', cascade: ['persist', 'remove'])]
    private ?Order $order = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScheduledFor(): ?\DateTime
    {
        return $this->scheduledFor;
    }

    public function setScheduledFor(?\DateTime $scheduledFor): static
    {
        $this->scheduledFor = $scheduledFor;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getAdvanceNoticeHours(): ?int { return $this->advanceNoticeHours; }
    public function setAdvanceNoticeHours(?int $hours): static 
    { 
        $this->advanceNoticeHours = $hours; 
        return $this; 
    }

    public function getAdvanceNoticeDays(): ?int { return $this->advanceNoticeDays; }
    public function setAdvanceNoticeDays(?int $days): static 
    { 
        $this->advanceNoticeDays = $days; 
        return $this; 
    }


    /**
     * Get human-readable scheduled time description
     */
    #[Groups(['order:read'])]
    public function getScheduledDescription(): ?string
    {
        if (!$this->scheduledFor) {
            return null;
        }

        $now = new \DateTime();
        $interval = $now->diff($this->scheduledFor);
        
        if ($interval->days == 0) {
            return "Today at " . $this->scheduledFor->format('g:i A');
        } elseif ($interval->days == 1) {
            return "Tomorrow at " . $this->scheduledFor->format('g:i A');
        } else {
            return $this->scheduledFor->format('l, F jS \a\t g:i A');
        }
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function isValidScheduledTime(): bool
    {
        if (!$this->scheduledFor) {
            return true;
        }

        $now = new \DateTime();
        
        // Can't schedule in the past
        if ($this->scheduledFor < $now) {
            return false;
        }

        // Check MINIMUM notice (hours)
        if ($this->advanceNoticeHours) {
            $minTime = (clone $now)->modify("+{$this->advanceNoticeHours} hours");
            if ($this->scheduledFor < $minTime) {
                return false; // Too soon!
            }
        }

        // Check MAXIMUM notice (days)
        if ($this->advanceNoticeDays) {
            $maxTime = (clone $now)->modify("+{$this->advanceNoticeDays} days");
            if ($this->scheduledFor > $maxTime) {
                return false; // Too far in advance!
            }
        }

        return true;
    }

}
