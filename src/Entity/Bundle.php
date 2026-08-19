<?php
namespace App\Entity;

use App\Repository\BundleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
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

#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(
            security: 'is_granted("ROLE_ADMIN")',
            denormalizationContext: [
                'groups' => ['bundle:write'],
                'allow_extra_attributes' => true
            ]
        ),
        new Put(
            security: 'is_granted("ROLE_ADMIN")',
            denormalizationContext: [
                'groups' => ['bundle:write'],
                'allow_extra_attributes' => true
            ]
        ),
        new Patch(
            security: 'is_granted("ROLE_ADMIN")',
            denormalizationContext: [
                'groups' => ['bundle:write','order:write'],
                'allow_extra_attributes' => true
            ]
        ),
        new Delete(security: 'is_granted("ROLE_ADMIN")'),    
    ],
    normalizationContext: ['groups' => ['bundle:read']],
    denormalizationContext: [  // Add this global context
        'groups' => ['bundle:write'],
        'allow_extra_attributes' => true
    ]
)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: BundleRepository::class)]
class Bundle
{
    #[Groups(['bundle:read', 'order:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['bundle:read', 'bundle:write', 'order:read'])]
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[Groups(['bundle:read', 'bundle:write', 'order:read'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[Groups(['bundle:read', 'bundle:write', 'order:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\Positive]
    private ?string $price = null;

    #[Groups(['bundle:read', 'bundle:write'])]
    #[ORM\Column]
    private ?bool $active = true;

    /**
     * @var Collection<int, Dish>
     */
    #[Groups(['bundle:read', 'bundle:write', 'order:read'])]
    #[ORM\ManyToMany(targetEntity: Dish::class, inversedBy: 'bundles')]
    private Collection $dishes;

    /**
     * @var Collection<int, Offer>
     */
    #[Groups(['bundle:read', 'bundle:write', 'order:read'])]
    #[ORM\OneToMany(targetEntity: Offer::class, mappedBy: 'bundle')]
    private Collection $offers;

    /**
     * @var Collection<int, BundleItem>
     */
    #[Groups(['bundle:read','bundle:write', 'order:read', 'order:write'])]
    #[ORM\OneToMany(targetEntity: BundleItem::class, mappedBy: 'bundle', cascade: ['persist', 'remove'])]
    private Collection $bundleItems;

    #[ORM\Column(nullable: true)]
    #[Groups(['bundle:read','bundle:write'])]
    private ?float $originalPrice = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['bundle:read','bundle:write'])]
    private ?float $savings = null;

    public function __construct()
    {
        $this->dishes = new ArrayCollection();
        $this->offers = new ArrayCollection();
        $this->bundleItems = new ArrayCollection();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function processBundleItems(): void
    {
        // Ensure all bundle items are properly linked to this bundle
        foreach ($this->bundleItems as $bundleItem) {
            if ($bundleItem->getBundle() !== $this) {
                $bundleItem->setBundle($this);
            }
        }
        
        // Clear and rebuild the dishes collection from bundle items
        // $this->dishes->clear();
        // foreach ($this->bundleItems as $bundleItem) {
        //     $dish = $bundleItem->getDish();
        //     if ($dish && !$this->dishes->contains($dish)) {
        //         $this->dishes->add($dish);
        //         $dish->addBundle($this);
        //     }
       // }
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;
        return $this;
    }

    /**
     * @return Collection<int, Dish>
     */
    public function getDishes(): Collection
    {
        return $this->dishes;
    }

    public function addDish(Dish $dish): static
    {
        if (!$this->dishes->contains($dish)) {
            $this->dishes->add($dish);
            $dish->addBundle($this);
        }
        return $this;
    }

    public function removeDish(Dish $dish): static
    {
        if ($this->dishes->removeElement($dish)) {
            $dish->removeBundle($this);
        }
        return $this;
    }

    /**
     * @return Collection<int, Offer>
     */
    public function getOffers(): Collection
    {
        return $this->offers;
    }

    public function addOffer(Offer $offer): static
    {
        if (!$this->offers->contains($offer)) {
            $this->offers->add($offer);
            $offer->setBundle($this);
        }
        return $this;
    }

    public function removeOffer(Offer $offer): static
    {
        if ($this->offers->removeElement($offer)) {
            if ($offer->getBundle() === $this) {
                $offer->setBundle(null);
            }
        }
        return $this;
    }

    #[Groups(['bundle:read', 'order:read'])]
    public function getTotalOriginalPrice(): float
    {
        $total = 0;
        foreach ($this->dishes as $dish) {
            $total += (float) $dish->getPrice();
        }
        $this->setOriginalPrice(round($total, 2));
        return round($total, 2);
    }

    #[Groups(['bundle:read', 'order:read'])]
    public function getSavings(): float
    {
        $this->setSavings($this->getTotalOriginalPrice() - (float) $this->price, 2);
        return round($this->getTotalOriginalPrice() - (float) $this->price, 2);
    }

    #[Groups(['bundle:read', 'order:read'])]
    public function getSavingsPercentage(): int
    {
        if ($this->getTotalOriginalPrice() === 0.0) {
            return 0;
        }
        return (int) round(($this->getSavings() / $this->getTotalOriginalPrice()) * 100);
    }

    /**
     * @return Collection<int, BundleItem>
     */
    public function getBundleItems(): Collection
    {
        return $this->bundleItems;
    }

    public function addBundleItem(BundleItem $bundleItem): static
    {
        if (!$this->bundleItems->contains($bundleItem)) {
            $this->bundleItems->add($bundleItem);
            $bundleItem->setBundle($this);
        }

        return $this;
    }

    public function removeBundleItem(BundleItem $bundleItem): static
    {
        if ($this->bundleItems->removeElement($bundleItem)) {
            if ($bundleItem->getBundle() === $this) {
                $bundleItem->setBundle(null);
            }
        }

        return $this;
    }

    public function getOriginalPrice(): ?float
    {
        return $this->originalPrice;
    }

    public function setOriginalPrice(?float $originalPrice): static
    {
        $this->originalPrice = $originalPrice;

        return $this;
    }

    public function setSavings(?float $savings): static
    {
        $this->savings = $savings;

        return $this;
    }
}