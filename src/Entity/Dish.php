<?php

namespace App\Entity;

use App\Repository\DishRepository;
use BcMath\Number;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
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
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Dto\DishReorderInput;
use App\State\DishReorderProcessor;
use Symfony\Component\Serializer\Annotation\MaxDepth;


#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'partial',
    'status' => 'exact'
])]

#[ApiResource(
    operations: [
        new Get(
            denormalizationContext: ['groups' => ['dish:write']],
            normalizationContext: ['groups' => ['dish:read']],
            cacheHeaders: [
                'max_age' => 60,
                'shared_max_age' => 60,
                'public' => true
            ]
        ),
        new GetCollection(
            filters: ['dish.dishType_filter'],
            denormalizationContext: ['groups' => ['dish:write', 'homepage:read']],
            normalizationContext: ['groups' => ['dish:read']],
            cacheHeaders: [
                'max_age' => 60,
                'shared_max_age' => 60,
                'public' => true
            ],
            order: ['sortId' => 'ASC', 'id' => 'ASC'] 
        ),
        new Post(denormalizationContext: ['groups' => ['dish:write']],
                normalizationContext: ['groups' => ['dish:read']]),
        new Put(),
        new Delete(security: 'is_granted("ROLE_ADMIN")',denormalizationContext: ['groups' => ['dish:write']]),
        new Post(
            uriTemplate: '/dishes/reorder.{_format}',
            input: DishReorderInput::class,
            security: 'is_granted("ROLE_ADMIN")',
            processor: DishReorderProcessor::class,
            name: 'dish_reorder'
        ),
        new Patch(
            denormalizationContext: ['groups' => ['dish:write']],
            normalizationContext: ['groups' => ['dish:read']],
            securityPostDenormalize: "is_granted('ROLE_ADMIN')",
            validationContext: ['groups' => ['dish:write']],
            security: 'is_granted("ROLE_ADMIN")'
        )
    ]
)]

#[ApiFilter(BooleanFilter::class, properties: ['hasVariant','active'])]

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: DishRepository::class)]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE', region: 'dishes')]
class Dish
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['dish:read', 'homepage:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?string $menu = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?int $price = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?int $discountedPrice = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?string $imageSource = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?int $brightness = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?int $opacity = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?bool $availableInCatering = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?\DateTime $updatedOn = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?string $status = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]      
    private ?bool $active = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?string $tag = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?int $contrast = null;

    #[Groups(['dish:read'])]
    #[ORM\ManyToOne(targetEntity: Offer::class, inversedBy: 'discountedDishes')]
    #[ORM\JoinColumn(nullable: true)]
    #[ORM\Cache]
    private ?Offer $activeOffer = null;

    #[Groups(['dish:read'])]
    public function getPromoStatus(): array
    {
        $isActive = false;
        $discountValue = 0;
        if($this->activeOffer){
            $isActive = $this->activeOffer->isActive() != null;
            
            
            if ($isActive && $this->activeOffer->getDiscount()) {
                $discount = $this->activeOffer->getDiscount();
                
                // Check if it's a BOGO offer
                if ($discount->getBogoType()) {
                    $discountValue = 'bogo'; // or you can return BOGO details
                } else {
                    $discountValue = $discount->getValue() ?? 0;
                }
            }
        }
        $description="";
        if($this->activeOffer){
            $description = $this->activeOffer->getDescription();
        }
        return [
            'active' => $isActive,
            'discount' => $discountValue,
            'description' => $description,
            'isBogo' => $isActive ? ($this->activeOffer->getDiscount()?->getBogoType() !='' || $this->activeOffer->getDiscount()?->getBogoType() !='') : false

            //'isBogo' => $isActive ? $this->activeOffer->getDiscount()?->getBogoType():''
        ];
    }


    /**
     * @var Collection<int, OrderItem>
     */ 
    #[Groups(['dish:read'])]
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'dish')]
    private Collection $orderItems;

    public function __construct()
    {   
        $this->orderItems = new ArrayCollection();
        $this->bundles = new ArrayCollection();
        $this->variants = new ArrayCollection();
    }  


    public function getCurrentDiscount(): ?float
    {
        return $this->activeOffer?->getDiscount()?->getValue();
    }

    

    public function setActiveOffer(?Offer $activeOffer): static
    {
        $this->activeOffer = $activeOffer;
        $this->updateDiscountedPrice();
        return $this;
    }

    public function getActiveOffer(): ?Offer
    {
        return $this->activeOffer;
    }

    #[Groups(['dish:read','dish:write','offer:write'])]
    public function setPriceFromVariant(?int $variantId): void
    {
        // If no variant ID provided or no variants exist, do nothing
        if ($variantId === null) {
            return;
        }
        
        $variants = $this->getVariants();
        
        // If dish has no variants, just return
        if ($variants->isEmpty()) {
            return;
        }
        
        foreach ($variants as $variant) {
            if ($variant->getId() === $variantId) {
                $this->setPrice($variant->getPrice());
                return;
            }
        }
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedOn = new \DateTime();
        if(count($this->variants) > 0)
        {
            $this->setHasVariant(true);
        }
        else
        {
            $this->setHasVariant(false);
        }
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateDiscountedPrice(): void
    {
        if ($this->price === null) {
            $this->discountedPrice = null;
            return;
        }

        // If there's an active offer with a discount, calculate discounted price
        if ($this->activeOffer && $this->activeOffer->isActive() && $discount = $this->activeOffer->getDiscount()) {
            $discountValue = $discount->getValue();
            
            // Assuming discount is a percentage (e.g., 20 for 20% off)
            if($discount->getDiscountType() == 'fixed_amount')
            {
                $this->discountedPrice = (int) max(0, $this->price - $discountValue);
            }
            elseif($discount->getDiscountType() == 'percentage')
            {
                $discountAmount = ($this->price * $discountValue) / 100;
                $this->discountedPrice = (int) max(0, $this->price - $discountAmount);
            }

            
        } else {
            // No active offer, reset to regular price
            $this->discountedPrice =null;
        }
    }

    /**
     * @var Collection<int, Bundle>
     */
    #[ORM\ManyToMany(targetEntity: Bundle::class, mappedBy: 'dishes')]
    private Collection $bundles;

    #[ORM\Column(nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?bool $enableAddToCart = null;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'dishes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['dish:read', 'dish:write'])]
    #[ORM\Cache]
    private ?Category $category = null;

    #[ORM\OneToMany(mappedBy: 'dish', targetEntity: Variant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['dish:read', 'homepage:read'])]
    #[ORM\Cache]
    #[MaxDepth(3)]
    private Collection $variants;

    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    #[ORM\Column(nullable: true)]
    private ?int $prepTime = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?bool $hasVariant = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?string $loyverseItemId = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?string $loyverseSku = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?\DateTimeImmutable $syncedAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $syncStatus = null;

    #[ORM\Column(nullable: true)]
    private ?float $availableStock = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $stockLastCheckedAt = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isAvailable = null;

    #[ORM\Column(nullable: true)]
    private ?float $customizedPrice = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?string $loyverseVariantId = null;

    #[ORM\Column(type: Types::ARRAY, nullable: true)]
    #[Groups(['dish:read','dish:write', 'homepage:read'])]
    private ?array $loyverseModifiersIds = null;

    #[ORM\Column(nullable: true)]
    private ?int $sortId = null;

    public function getLoyverseVariantId(): ?string
    {
        return $this->loyverseVariantId;
    }

    public function setLoyverseVariantId(?string $loyverseVariantId): static
    {
        $this->loyverseVariantId = $loyverseVariantId;
        return $this;
    }


    /**
     * @return Collection<int, Bundle>
     */
    public function getBundles(): Collection
    {
        return $this->bundles;
    }

    public function addBundle(Bundle $bundle): static
    {
        if (!$this->bundles->contains($bundle)) {
            $this->bundles->add($bundle);
            $bundle->addDish($this);
        }
        return $this;
    }

    public function removeBundle(Bundle $bundle): static
    {
        if ($this->bundles->removeElement($bundle)) {
            $bundle->removeDish($this);
        }
        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;
        return $this;
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getMenu(): ?string
    {
        return $this->menu;
    }

    public function setMenu(?string $menu): static
    {
        $this->menu = $menu;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
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

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): static
    {
        $this->price = $price;

        return $this;
    }

    #[Groups(['dish:read'])]
    public function getDiscountedPrice(): ?int
    {
        return $this->discountedPrice;
    }

    #[Groups(['dish:read','dish:write','offer:write'])]
    public function setDiscountedPrice(?int $discountedPrice): static
    {
        $this->discountedPrice = $discountedPrice;

        return $this;
    }

    public function getImageSource(): ?string
    {
        return $this->imageSource;
    }

    public function setImageSource(?string $imageSource): static
    {
        $this->imageSource = $imageSource;

        return $this;
    }

    public function getBrightness(): ?int
    {
        return $this->brightness;
    }

    public function setBrightness(?int $brightness): static
    {
        $this->brightness = $brightness;

        return $this;
    }

    public function getOpacity(): ?int
    {
        return $this->opacity;
    }

    public function setOpacity(?int $opacity): static
    {
        $this->opacity = $opacity;

        return $this;
    }

    public function getAvailableInCatering(): ?bool
    {
        return $this->availableInCatering;
    }

    public function setAvailableInCatering(?bool $availableInCatering): static
    {
        $this->availableInCatering = $availableInCatering;

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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function setTag(?string $tag): static
    {
        $this->tag = $tag;

        return $this;
    }

    public function getContrast(): ?int
    {
        return $this->contrast;
    }

    public function setContrast(?int $contrast): static
    {
        $this->contrast = $contrast;

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    public function addOrderItem(OrderItem $orderItem): static
    {
        if (!$this->orderItems->contains($orderItem)) {
            $this->orderItems->add($orderItem);
            $orderItem->addDish($this);
        }

        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): static
    {
        if ($this->orderItems->removeElement($orderItem)) {
            $orderItem->removeDish($this);
        }

        return $this;
    }

    public function getEnableAddToCart(): ?bool
    {
        return $this->enableAddToCart;
    }

    public function setEnableAddToCart(?bool $enableAddToCart): static
    {
        $this->enableAddToCart = $enableAddToCart;

        return $this;
    }

    /**
     * @return Collection<int, Variant>
     */
    public function getVariants(): Collection
    {
        return $this->variants;
    }

    public function addVariant(Variant $priceVariant): self
    {
        if (!$this->variants->contains($priceVariant)) {
            $this->variants->add($priceVariant);
            $priceVariant->setDish($this);
        }
        return $this;
    }

    public function removeVariant(Variant $priceVariant): self
    {
        if ($this->variants->removeElement($priceVariant)) {
            if ($priceVariant->getDish() === $this) {
                $priceVariant->setDish(null);
            }
        }
        return $this;
    }

    public function getPrepTime(): ?int
    {
        return $this->prepTime;
    }

    public function setPrepTime(?int $prepTime): static
    {
        $this->prepTime = $prepTime;

        return $this;
    }

    public function hasVariant(): ?bool
    {
        return $this->hasVariant;
    }

    public function setHasVariant(?bool $hasVariant): static
    {
        $this->hasVariant = $hasVariant;

        return $this;
    }

    public function getLoyverseItemId(): ?string
    {
        return $this->loyverseItemId;
    }

    public function setLoyverseItemId(?string $loyverseItemId): static
    {
        $this->loyverseItemId = $loyverseItemId;

        return $this;
    }

    public function getLoyverseSku(): ?string
    {
        return $this->loyverseSku;
    }

    public function setLoyverseSku(?string $loyverseSku): static
    {
        $this->loyverseSku = $loyverseSku;

        return $this;
    }

    public function getSyncedAt(): ?\DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(?\DateTimeImmutable $syncedAt): static
    {
        $this->syncedAt = $syncedAt;

        return $this;
    }

    public function getSyncStatus(): ?string
    {
        return $this->syncStatus;
    }

    public function setSyncStatus(?string $syncStatus): static
    {
        $this->syncStatus = $syncStatus;

        return $this;
    }

    public function getAvailableStock(): ?float
    {
        return $this->availableStock;
    }

    public function setAvailableStock(?float $availableStock): static
    {
        $this->availableStock = $availableStock;

        return $this;
    }

    public function getStockLastCheckedAt(): ?\DateTimeImmutable
    {
        return $this->stockLastCheckedAt;
    }

    public function setStockLastCheckedAt(?\DateTimeImmutable $stockLastCheckedAt): static
    {
        $this->stockLastCheckedAt = $stockLastCheckedAt;

        return $this;
    }

    public function isAvailable(): ?bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(?bool $isAvailable): static
    {
        $this->isAvailable = $isAvailable;

        return $this;
    }

    public function getCustomizedPrice(): ?float
    {
        return $this->customizedPrice;
    }

    public function setCustomizedPrice(?float $customizedPrice): static
    {
        $this->customizedPrice = $customizedPrice;

        return $this;
    }

    public function getLoyverseModifiersIds(): ?array
    {
        return $this->loyverseModifiersIds;
    }

    public function setLoyverseModifiersIds(?array $loyverseModifiersIds): static
    {
        $this->loyverseModifiersIds = $loyverseModifiersIds;

        return $this;
    }

    public function getSortId(): ?int
    {
        return $this->sortId;
    }

    public function setSortId(?int $sortId): static
    {
        $this->sortId = $sortId;

        return $this;
    }


}
