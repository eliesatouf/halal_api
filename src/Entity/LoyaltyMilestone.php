<?php
// App\Entity\LoyaltyMilestone
namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use App\Repository\LoyaltyMilestoneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: LoyaltyMilestoneRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: 'is_granted("ROLE_ADMIN")'),
        new Patch(security: 'is_granted("ROLE_ADMIN")')
    ],
    normalizationContext: ['groups' => ['milestone:read']],
    denormalizationContext: ['groups' => ['milestone:write']]
)]
class LoyaltyMilestone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['milestone:read', 'reward:read', 'customer:profile'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['milestone:read','milestone:write', 'reward:read', 'customer:profile'])]
    private ?string $name = null;

    #[ORM\Column]
    #[Groups(['milestone:read', 'milestone:write', 'reward:read', 'customer:profile'])]
    private ?int $visitCount = null;

    #[ORM\Column(length: 50)]
    #[Groups(['milestone:read', 'milestone:write', 'reward:read', 'customer:profile'])]
    private ?string $rewardType = null; // 'percentage_discount', 'free_dish'

    #[ORM\Column(nullable: true)]
    #[Groups(['milestone:read', 'milestone:write', 'reward:read', 'customer:profile'])]
    private ?float $rewardValue = null; // 10 for 10%, null for free dish

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['milestone:read', 'milestone:write', 'reward:read', 'customer:profile'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['milestone:read', 'milestone:write'])]
    private ?bool $active = true;

    #[ORM\ManyToMany(targetEntity: Dish::class)]
    #[ORM\JoinTable(name: 'milestone_eligible_dishs')]
    #[Groups(['milestone:read','milestone:write', 'reward:read', 'customer:profile'])]
    private Collection $eligibleDishes;

    #[ORM\Column]
    #[Groups(['milestone:read'])]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['milestone:read'])]
    private ?\DateTime $updatedAt = null;

    public function __construct()
    {
        $this->eligibleDishes = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    // Getters and setters
    public function getId(): ?int { return $this->id; }
    
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    
    public function getVisitCount(): ?int { return $this->visitCount; }
    public function setVisitCount(int $visitCount): static { $this->visitCount = $visitCount; return $this; }
    
    public function getRewardType(): ?string { return $this->rewardType; }
    public function setRewardType(string $rewardType): static { $this->rewardType = $rewardType; return $this; }
    
    public function getRewardValue(): ?float { return $this->rewardValue; }
    public function setRewardValue(?float $rewardValue): static { $this->rewardValue = $rewardValue; return $this; }
    
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    
    public function isActive(): ?bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }
    
    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $createdAt): static { $this->createdAt = $createdAt; return $this; }
    
    public function getUpdatedAt(): ?\DateTime { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTime $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    /**
     * @return Collection<int, Dish>
     */
    public function getEligibleDishes(): Collection
    {
        return $this->eligibleDishes;
    }

    public function addEligibleDish(Dish $dish): static
    {
        if (!$this->eligibleDishes->contains($dish)) {
            $this->eligibleDishes->add($dish);
        }
        return $this;
    }

    public function removeEligibleDish(Dish $dish): static
    {
        $this->eligibleDishes->removeElement($dish);
        return $this;
    }

    public function isDishEligible(Dish $dish): bool
    {
        // If no specific dishs are assigned, reward applies to all dishs
        if ($this->eligibleDishes->isEmpty()) {
            return true;
        }
        
        return $this->eligibleDishes->contains($dish);
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}