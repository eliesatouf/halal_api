<?php

namespace App\Entity;

use App\Repository\CustomerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection; 
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Symfony\Bundle\SecurityBundle\Security;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Serializer\Filter\PropertyFilter;

#[ApiResource(
    operations: [
        new Get(security:'is_granted("ROLE_ADMIN") or is_granted("ROLE_USER") and object.getUser() == user'),
        new GetCollection(filters: ['customer.customer_search_filter']),
        new Post(security:'is_granted("ROLE_ADMIN") or is_granted("ROLE_USER") and object.getUser() == user'),
        new Put(security:'is_granted("ROLE_ADMIN")'),
        new Delete(security:'is_granted("ROLE_ADMIN")'),
        new Patch(security:'is_granted("ROLE_ADMIN") or is_granted("ROLE_USER") and object.getUser() == user' ),
    ],
    normalizationContext: ['groups' => ['customer:read']],
    denormalizationContext: ['groups' => ['customer:write']]
)]


#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'partial',
    'email' => 'partial',
    'telephone' => 'exact'
])]


#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: CustomerRepository::class)]
class Customer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['customer:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['customer:read'])]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Groups(['customer:read','customer:write'])]
    private ?string $name = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['customer:read','customer:write'])]
    private ?string $address = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['customer:read'])]
    private ?bool $active = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['customer:read'])]
    private ?string $status = null;

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'customer')]
    #[Groups(['customer:read'])]
    private Collection $order;

    #[ORM\Column(nullable: true)]
    #[Groups(['customer:read'])]
    private ?\DateTime $createdOn = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['customer:read'])]
    private ?\DateTime $updatedOn = null;

    #[ORM\OneToOne(mappedBy: 'customer', cascade: ['persist', 'remove'])]
    #[Groups(['customer:read'])]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['customer:read','customer:write'])]
    private ?string $telephone = null;

    #[ORM\OneToOne(mappedBy: 'customer', cascade: ['persist', 'remove'] , fetch: 'EAGER')]
    #[Groups(['customer:read'])]
    #[MaxDepth(1)]
    private ?FirstOrder $firstOrder = null;


    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['customer:read','customer:write'])]
    private ?string $loyverseCustomerId = null;

    public function getLoyverseCustomerId(): ?string
    {
        return $this->loyverseCustomerId;
    }

    public function setLoyverseCustomerId(?string $loyverseCustomerId): static
    {
        $this->loyverseCustomerId = $loyverseCustomerId;
        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedOn = new \DateTime();
    }


    public function __construct()
    {
        $this->order = new ArrayCollection();
        $this->loyaltyRewards = new ArrayCollection();

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getMyorder(): Collection
    {
        return $this->order;
    }

    public function addMyorder(Order $order): static
    {
        if (!$this->order->contains($order)) {
            $this->order->add($order);
            $order->setCustomer($this);
        }

        return $this;
    }

    public function removeMyorder(Order $order): static
    {
        if ($this->order->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getCustomer() === $this) {
                $order->setCustomer(null);
            }
        }

        return $this;
    }

    public function getCreatedOn(): ?\DateTime
    {
        return $this->createdOn;
    }

    public function setCreatedOn(?\DateTime $createdOn): static
    {
        $this->createdOn = $createdOn;

        return $this;
    }

    public function getUpdatedOn(): ?\DateTime
    {
        return $this->updatedOn;
    }

    public function setUpdatedOn(?\DateTime $updatedOn): static
    {
        $this->updatedOn = $updatedOn;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        // unset the owning side of the relation if necessary
        if ($user === null && $this->user !== null) {
            $this->user->setCustomer(null);
        }

        // set the owning side of the relation if necessary
        if ($user !== null && $user->getCustomer() !== $this) {
            $user->setCustomer($this);
        }

        $this->user = $user;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getFirstOrder(): ?FirstOrder
    {
        return $this->firstOrder;
    }

    public function setFirstOrder(?FirstOrder $firstOrder): static
    {
        // unset the owning side of the relation if necessary
        if ($firstOrder === null && $this->firstOrder !== null) {
            $this->firstOrder->setCustomer(null);
        }

        // set the owning side of the relation if necessary
        if ($firstOrder !== null && $firstOrder->getCustomer() !== $this) {
            $firstOrder->setCustomer($this);
        }

        $this->firstOrder = $firstOrder;

        return $this;
    }

    public function getFirstOrderSafe()
    {
        //$firstOrder = $this->firstOrder->getDiscountValue();
        $firstOrder = $this->firstOrder->getDiscountValue();
        
        return $firstOrder;
    }

     ### loyalty ####
    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['customer:write','appointment:read','appointment:write'])]
    private ?int $visitCount = 0;

    #[Groups(['customer:read','appointment:read','appointment:write'])]
    public function getVisitCount(): ?int { return $this->visitCount; }
    public function setVisitCount(int $visitCount): static { $this->visitCount = $visitCount; return $this; }

    public function incrementVisitCount(): static { $this->visitCount++; return $this; }

    /**
     * @return array<LoyaltyReward>
     */
    #[Groups(['customer:read', 'loyalty:read'])]
    public function getActiveRewards(): array
    {
        return $this->loyaltyRewards->filter(function(LoyaltyReward $reward) {
            return $reward->getStatus() === 'active' 
                && $reward->getExpiresAt() > new \DateTime();
        })->toArray();
    }

    #[ORM\OneToMany(targetEntity: LoyaltyReward::class, mappedBy: 'customer')]
    #[Groups(['customer:read', 'loyalty:read'])]
    private Collection $loyaltyRewards;

    /**
     * @return Collection<int, LoyaltyReward>
     */
    public function getLoyaltyRewards(): Collection
    {
        return $this->loyaltyRewards;
    }

    public function hasUsedFirstOrderDiscount(): bool
    {
        $firstOrder = $this->getFirstOrder();
        return $firstOrder && $firstOrder->getDiscountApplied();
    }

    public function getFirstOrderDiscountPercentage(): ?int
    {
        $firstOrder = $this->getFirstOrder();
        if ($firstOrder && $firstOrder->getDiscountApplied()) {
            // The discount value is stored as the actual amount, not percentage
            // We need to calculate percentage based on order amount
            return null; // Can't determine percentage without order context
        }
        return null;
    }

    
}
