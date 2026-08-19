<?php

namespace App\Entity;

use App\Repository\BlockOfferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;

#[ApiResource(
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['asset:write']],
    operations: [
        new Get(),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new GetCollection(
            filters: [ActiveBlockOfferFilter::class]
        ),
        new Post(security: "is_granted('ROLE_ADMIN')")
    ]
)]
// #[Get]
// #[Put(securityPostDenormalize: "is_granted('ROLE_ADMIN')")] 
// #[GetCollection(
//     filters: ['App\Filter\ActiveBlockOfferFilter']
// )]
// #[Post(security: "is_granted('ROLE_ADMIN')")]
// #[Patch(security: "is_granted('ROLE_ADMIN')")]

#[ORM\Entity(repositoryClass: BlockOfferRepository::class)]
class BlockOffer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageSource = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageName = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $link = null;

    #[ORM\Column(nullable: true)]
    private ?float $priceWas = null;

    #[ORM\Column(nullable: true)]
    private ?float $priceNow = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $tage = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $endDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $hoursFrom = null;

    #[ORM\Column(nullable: true)]
    private ?int $hoursTo = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $dayFrom = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $dayTo = null;

    #[ORM\Column(nullable: true)]
    private ?bool $active = null;

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

    public function getImageSource(): ?string
    {
        return $this->imageSource;
    }

    public function setImageSource(?string $imageSource): static
    {
        $this->imageSource = $imageSource;

        return $this;
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    public function setImageName(?string $imageName): static
    {
        $this->imageName = $imageName;

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

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): static
    {
        $this->link = $link;

        return $this;
    }

    public function getPriceWas(): ?float
    {
        return $this->priceWas;
    }

    public function setPriceWas(?float $priceWas): static
    {
        $this->priceWas = $priceWas;

        return $this;
    }

    public function getPriceNow(): ?float
    {
        return $this->priceNow;
    }

    public function setPriceNow(?float $priceNow): static
    {
        $this->priceNow = $priceNow;

        return $this;
    }

    public function getTage(): ?string
    {
        return $this->tage;
    }

    public function setTage(?string $tage): static
    {
        $this->tage = $tage;

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

    public function getHoursFrom(): ?int
    {
        return $this->hoursFrom;
    }

    public function setHoursFrom(?int $hoursFrom): static
    {
        $this->hoursFrom = $hoursFrom;

        return $this;
    }

    public function getHoursTo(): ?int
    {
        return $this->hoursTo;
    }

    public function setHoursTo(?int $hoursTo): static
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

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): static
    {
        $this->active = $active;

        return $this;
    }
}
