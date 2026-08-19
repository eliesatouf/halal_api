<?php
namespace App\Entity;

use App\Repository\DiscountRepository;
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

#[ApiResource(
    operations: [
        new Get(security:"is_granted('ROLE_USER')"),
        
        new GetCollection(
                filters: ['offer.active_filter']),

        new Post(security: 'is_granted("ROLE_ADMIN")'),
        new Put(security: 'is_granted("ROLE_ADMIN")'),
        new Delete(security: 'is_granted("ROLE_ADMIN")'),
        new Patch(security: 'is_granted("ROLE_ADMIN")')
    ]
)]

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: DiscountRepository::class)]
class Discount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $code = null;
    
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $discountType = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $value = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $minOrderAmount = null;

    #[ORM\Column(nullable: true)]
    private ?bool $active = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $startDate = null;

    // #[ORM\Column(nullable: true)]
    // private ?\DateTime $endDate = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $createdOn = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $updatedOn = null;

    //--- BOGO --//
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $bogoType = null; // 'free', 'discount', 'same'

    #[ORM\Column(nullable: true)]
    private ?int $bogoBuyQuantity = null;

    #[ORM\Column(nullable: true)]
    private ?int $bogoGetQuantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $bogoDiscountValue = null;

    #[ORM\OneToOne(mappedBy: 'discount')]
    private ?Offer $offer = null;

    public function getBogoType(): ?string
    {
        return $this->bogoType;
    }

    public function setBogoType(?string $bogoType): static
    {
        $this->bogoType = $bogoType;
        return $this;
    }

    public function getBogoBuyQuantity(): ?int
    {
        return $this->bogoBuyQuantity;
    }

    public function setBogoBuyQuantity(?int $bogoBuyQuantity): static
    {
        $this->bogoBuyQuantity = $bogoBuyQuantity;
        return $this;
    }

    public function getBogoGetQuantity(): ?int
    {
        return $this->bogoGetQuantity;
    }

    public function setBogoGetQuantity(?int $bogoGetQuantity): static
    {
        $this->bogoGetQuantity = $bogoGetQuantity;
        return $this;
    }

    public function getBogoDiscountValue(): ?string
    {
        return $this->bogoDiscountValue;
    }

    public function setBogoDiscountValue(?string $bogoDiscountValue): static
    {
        $this->bogoDiscountValue = $bogoDiscountValue;
        return $this;
    }

    //-- end of BOGO --//


    //-- BUY X GET Y --//

    #[ORM\Column(nullable: true)]
    private ?int $buyQuantity = null;

    #[ORM\Column(nullable: true)]
    private ?int $getQuantity = null;

    public function getBuyQuantity(): ?int
    {
        return $this->buyQuantity;
    }

    public function setBuyQuantity(?int $buyQuantity): static
    {
        $this->buyQuantity = $buyQuantity;
        return $this;
    }

    public function getGetQuantity(): ?int
    {
        return $this->getQuantity;
    }

    public function setGetQuantity(?int $getQuantity): static
    {
        $this->getQuantity = $getQuantity;
        return $this;
    }

    //-- END OF BUY X GET Y --//


    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        if(!$this->createdOn){
            $this->createdOn = new \DateTime();
        }
        else{
            $this->updatedOn = new \DateTime();
        }
        
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getDiscountType(): ?string
    {
        return $this->discountType;
    }

    public function setDiscountType(?string $discountType): static
    {
        $this->discountType = $discountType;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getMinOrderAmount(): ?string
    {
        return $this->minOrderAmount;
    }

    public function setMinOrderAmount(?string $minOrderAmount): static
    {
        $this->minOrderAmount = $minOrderAmount;

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

    // public function getStartDate(): ?\DateTime
    // {
    //     return $this->startDate;
    // }

    // public function setStartDate(?\DateTime $startDate): static
    // {
    //     $this->startDate = $startDate;

    //     return $this;
    // }

    // public function getEndDate(): ?\DateTime
    // {
    //     return $this->endDate;
    // }

    // public function setEndDate(?\DateTime $endDate): static
    // {
    //     $this->endDate = $endDate;

    //     return $this;
    // }

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

    public function getOffer(): ?Offer
    {
        return $this->offer;
    }

    public function setOffer(?Offer $offer): static
    {
        // unset the owning side of the relation if necessary
        if ($offer === null && $this->offer !== null) {
            $this->offer->setDiscount(null);
        }

        // set the owning side of the relation if necessary
        if ($offer !== null && $offer->getDiscount() !== $this) {
            $offer->setDiscount($this);
        }

        $this->offer = $offer;

        return $this;
    }

}
