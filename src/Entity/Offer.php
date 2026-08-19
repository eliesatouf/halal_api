<?php

namespace App\Entity;

use App\Repository\OfferRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
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
use App\Filter\ActiveOfferFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use Psr\Log\LoggerInterface;


#[ApiFilter(BooleanFilter::class, properties: ['active','assignedToHomePage'])]

#[ApiResource(
    operations: [   
        new Get( ),
        new GetCollection(
            // cacheHeaders: [
            //     'max_age' => 3600,
            //     'shared_max_age' => 3600,
            //     'vary' => ['Accept', 'Accept-Language']
            // ]
           // filters: ['offer.active_filter'] 
        ),
        new Post(),
        new Put(),
        new Delete(security: 'is_granted("ROLE_ADMIN")'),
        new Patch(
            securityPostDenormalize: "is_granted('ROLE_ADMIN')",
            validationContext: ['groups' => ['offer:write']],
            security: 'is_granted("ROLE_ADMIN")',
            denormalizationContext: ['groups' => ['offer:write', 'offer:write:discounted']], 
            normalizationContext: ['groups' => ['offer:read', 'homepage:read']]
        )
    ],
    denormalizationContext: ['groups' => ['offer:write']],
    normalizationContext: ['groups' => ['offer:read', 'homepage:read']]
)]

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: OfferRepository::class)]
class Offer
{
    
    const TYPE_BOGO = 'bogo';
    const TYPE_BUNDLE = 'bundle';
    const TYPE_FREE_GIFT = 'free_gift';
    const TYPE_SEASONAL = 'seasonal';
    const TYPE_DISCOUNT = 'discount';
    
    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageSource = null;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $link = null;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Column(nullable: true)]
    private ?int $priceWas = null;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Column(nullable: true)]
    private ?int $priceNow = null;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tag = null;

    #[Groups(['offer:read','offer:write'])]
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $startDate = null;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $endDate = null;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $hoursFrom = null;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $hoursTo = null;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dayFrom = null;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dayTo = null;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Column(nullable: true)]
    private ?bool $assignedToHomePage = null;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Column(nullable: true)]
    private ?bool $active = null;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Column(nullable: true)]
    private ?\DateTime $updatedOn = null;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\OneToOne(inversedBy: 'offer')]
    private ?Discount $discount = null;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type = null;

    #[Groups(['offer:read', 'homepage:read', 'offer:write:discounted'])]
    #[ORM\ManyToMany(targetEntity: Dish::class)]  
    #[ORM\JoinTable(name: 'offer_discounte_dishes')]
    private Collection $discountedDishes;  

    //-- BOGO --//

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Column(nullable: true)]
    private ?bool $isBogo = null;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\ManyToMany(targetEntity: Dish::class)]
    #[ORM\JoinTable(name: 'offer_bogo_eligible_dishes')]
    private Collection $bogoEligibleDishes;

    #[Groups(['offer:read','offer:write', 'homepage:read'])]
    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $freeShipping = false;

    public function getFreeShipping(): ?bool
    {
        return $this->freeShipping;
    }

    public function setFreeShipping(?bool $freeShipping): static
    {
        $this->freeShipping = $freeShipping;
        return $this;
    }

    public function __construct(

    )
    {
        $this->discountedDishes = new ArrayCollection();
        $this->bogoEligibleDishes = new ArrayCollection();

    }

    public function getIsBogo(): ?bool
    {
        return $this->isBogo;
    }

    public function setIsBogo(?bool $isBogo): static
    {
        $this->isBogo = $isBogo;
        return $this;
    }

    /**
     * @return Collection<int, Dish>
     */
    public function getBogoEligibleDishes(): Collection
    {
        return $this->bogoEligibleDishes;
    }

    public function addBogoEligibleDish(Dish $dish): static
    {
        if (!$this->bogoEligibleDishes->contains($dish)) {
            $this->bogoEligibleDishes->add($dish);
            $dish->setActiveOffer($this);
            $dish->updateDiscountedPrice();

        }
        return $this;
    }

    public function removeBogoEligibleDish(Dish $dish): static
    {
        $this->bogoEligibleDishes->removeElement($dish);
        if ($dish->getActiveOffer() === $this) {
            $dish->setActiveOffer(null);
            $dish->updateDiscountedPrice();
        }
        return $this;
    }


    //-- END BOGO --//

    //-- BUNDLE --//

    #[Groups(['offer:read', 'offer:write', 'homepage:read'])]
    #[ORM\ManyToOne(targetEntity: Bundle::class, inversedBy: 'offers')]
    private ?Bundle $bundle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bogoType = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['offer:read', 'offer:write', 'homepage:read'])]
    private ?bool $enableAddToCart = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['offer:read', 'offer:write', 'homepage:read'])]
    private ?float $deliveryFee = null;

    public function getBundle(): ?Bundle
    {
        return $this->bundle;
    }

    public function setBundle(?Bundle $bundle): static
    {
        $this->bundle = $bundle;
        return $this;
    }

    // #[ORM\PrePersist]
    // #[ORM\PreUpdate]
    // public function updateDishActiveOffers(): void
    // {
    //     // Only process bundle offers

    //     // if ($this->getType() != '/api/offerTypes/3' || !$this->getBundle()) {
    //     //    // return;
    //     // }
    //     if ($this->getType() == '/api/offerTypes/3' )

    //     {
    //         $bundle = $this->getBundle();
        
    //         // Set this offer as activeOffer for all dishes in the bundle
    //         foreach ($bundle->getDishes() as $dish) {
    //             // Only set if this offer is active or there's no current active offer
    //             if ($this->isActive() || !$dish->getActiveOffer()) {
    //                 $dish->setActiveOffer($this);
    //                 $dish->setDiscountedPrice(null);
    //             }
    //         }
    //     }  
    // }

    #[ORM\PreRemove]
    public function removeDishActiveOffers(): void
    {
    
        $bundle = $this->getBundle();
        if (!$bundle) {
            error_log("Offer {$this->getId()} has no bundle, skipping removal");
            return;
        }
         error_log("Processing bundle removal for offer {$this->getId()} with bundle {$bundle->getId()}");
        // Remove this offer from all dishes in the bundle
        foreach ($bundle->getDishes() as $dish) {
            error_log("Checking dish {$dish->getId()} with activeOffer: " . ($dish->getActiveOffer() ? $dish->getActiveOffer()->getId() : 'null'));
            if ($dish->getActiveOffer() == $this) {
                 error_log("Clearing offer from dish {$dish->getId()}");
                $dish->setActiveOffer(null);
               $dish->updateDiscountedPrice(null); 

            }
        }
    }

    private function getOfferTypeName(?string $typeIri): ?string
    {
        $typeId = explode('/', $typeIri)[3];
        $offerType = $this->offerTypeRepository->find($typeId)->getName();
        return $offerType;
    }

    public function getOfferType(): string
    {
        $discount = $this->getDiscount();
        
        if ($this->getIsBogo()) {
            return 'bogo';
        }
        
        if ($discount && $discount->getBuyQuantity() && $discount->getGetQuantity()) {
            return 'buy_x_get_y';
        }
        
        return 'regular_discount';
    }


    //-- END BUNDLE --//


    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedOn = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getImageSource(): ?string
    {
        return $this->imageSource;
    }

    public function setImageSource(?string $imageSource): static
    {
        $this->imageSource = $imageSource;

        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): static
    {
        $this->link = $link;

        return $this;
    }

    public function getPriceWas(): ?int
    {
        return $this->priceWas;
    }

    public function setPriceWas(?int $priceWas): static
    {
        $this->priceWas = $priceWas;

        return $this;
    }

    public function getPriceNow(): ?int
    {
        return $this->priceNow;
    }

    public function setPriceNow(?int $priceNow): static
    {
        $this->priceNow = $priceNow;

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

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTime $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTime $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getHoursFrom(): ?string
    {
        return $this->hoursFrom;
    }

    public function setHoursFrom(?string $hoursFrom): static
    {
        $this->hoursFrom = $hoursFrom;

        return $this;
    }

    public function getHoursTo(): ?string
    {
        return $this->hoursTo;
    }

    public function setHoursTo(?string $hoursTo): static
    {
        $this->hoursTo = $hoursTo;

        return $this;
    }

    public function getDayFrom(): ?string
    {
        return $this->dayFrom;
    }

    public function setDayFrom(?string $dayFrom): static
    {
        $this->dayFrom = $dayFrom;

        return $this;
    }

    public function getDayTo(): ?string
    {
        return $this->dayTo;
    }

    public function setDayTo(?string $dayTo): static
    {
        $this->dayTo = $dayTo;

        return $this;
    }

    public function isAssignedToHomePage(): ?bool
    {
        return $this->assignedToHomePage;
    }

    public function setAssignedToHomePage(?bool $assignedToHomePage): static
    {
        $this->assignedToHomePage = $assignedToHomePage;

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

    public function getUpdatedOn(): ?\DateTime
    {
        return $this->updatedOn;
    }

    public function setUpdatedOn(?\DateTime $updatedOn): static
    {
        $this->updatedOn = $updatedOn;

        return $this;
    }

    public function getDiscount(): ?Discount
    {
        return $this->discount;
    }

    public function setDiscount(?Discount $discount): static
    {
        $this->discount = $discount;

        return $this;
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

    /**
     * @return Collection<int, Dish>
     */
    public function getDiscountedDishes(): Collection
    {
        return $this->discountedDishes;
    }

    public function addDiscountedDish(Dish $dish): static
    {
        if (!$this->discountedDishes->contains($dish)) {
            $this->discountedDishes->add($dish);
            $dish->setActiveOffer($this);
        }
        return $this;
    }

    public function removeDiscountedDish(Dish $dish): static
    {
        if ($this->discountedDishes->removeElement($dish)) {
            // set the owning side to null (unless already changed)
            if ($dish->getActiveOffer() === $this) {
                $dish->setActiveOffer(null);
                $dish->updateDiscountedPrice();
            }
        }
        return $this;
    }

    public function getBogoType(): ?string
    {
        return $this->bogoType;
    }

    public function setBogoType(?string $bogoType): static
    {
        $this->bogoType = $bogoType;

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

    public function getDeliveryFee(): ?float
    {
        return $this->deliveryFee;
    }

    public function setDeliveryFee(?float $deliveryFee): static
    {
        $this->deliveryFee = $deliveryFee;

        return $this;
    }

}
