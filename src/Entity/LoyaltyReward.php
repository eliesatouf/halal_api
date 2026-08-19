<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use App\Repository\LoyaltyRewardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use App\State\ClaimRewardProcessor;

#[ORM\Entity]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: 'is_granted("ROLE_ADMIN")'),
        new Patch(
            uriTemplate: '/rewards/{id}/claim',
            processor: ClaimRewardProcessor::class,
            security: 'is_granted("ROLE_USER")'
        ),
        new Patch(security: 'is_granted("ROLE_ADMIN")')
    ],
    normalizationContext: ['groups' => ['reward:read']],
    denormalizationContext: ['groups' => ['reward:write']]
)]
class LoyaltyReward
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['reward:read', 'customer:profile', 'order_preview'])] 
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['reward:read', 'customer:profile', 'order_preview'])]
    private ?Customer $customer = null;

    #[ORM\ManyToOne(targetEntity: LoyaltyMilestone::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['reward:read', 'customer:profile', 'order_preview'])]
    private ?LoyaltyMilestone $milestone = null;

    #[ORM\Column(length: 20)]
   #[Groups(['reward:read', 'customer:profile', 'order_preview'])]
    private ?string $status = 'active'; // active, used, expired

    #[ORM\Column(nullable: true)]
    #[Groups(['reward:read', 'customer:profile', 'order_preview'])]
    private ?\DateTime $claimedAt = null;

    #[ORM\Column]
    #[Groups(['reward:read', 'customer:profile', 'order_preview'])]
    private ?\DateTime $expiresAt = null;

    #[ORM\Column]
    #[Groups(['reward:read', 'customer:profile', 'order_preview'])]
    private ?\DateTime $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->expiresAt = (new \DateTime())->modify('+3 months');
        $this->accrude = new ArrayCollection();
    }

    // Getters and setters...
    public function getId(): ?int { return $this->id; }
    
    public function getCustomer(): ?Customer { return $this->customer; }
    public function setCustomer(?Customer $customer): static { $this->customer = $customer; return $this; }
    
    public function getMilestone(): ?LoyaltyMilestone { return $this->milestone; }
    public function setMilestone(?LoyaltyMilestone $milestone): static { $this->milestone = $milestone; return $this; }
    
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    
    public function getClaimedAt(): ?\DateTime { return $this->claimedAt; }
    public function setClaimedAt(?\DateTime $claimedAt): static { $this->claimedAt = $claimedAt; return $this; }
    
    public function getExpiresAt(): ?\DateTime { return $this->expiresAt; }
    public function setExpiresAt(\DateTime $expiresAt): static { $this->expiresAt = $expiresAt; return $this; }
    
    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $createdAt): static { $this->createdAt = $createdAt; return $this; }

    
}