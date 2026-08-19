<?php

namespace App\Entity;

use App\Repository\FeaturedItemRepository;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use Symfony\Component\Serializer\Annotation\Groups;


#[ApiResource(
    normalizationContext: ['groups' => ['featured:read', 'homepage:read']],
    security: "is_granted('PUBLIC_ACCESS')"
)]
#[Get]
#[Put(securityPostDenormalize: "is_granted('ROLE_ADMIN')")] 
#[GetCollection(security: "is_granted('PUBLIC_ACCESS')")]
#[Post(security: "is_granted('ROLE_ADMIN')")]
#[Patch(security: "is_granted('ROLE_ADMIN')")]

#[ORM\Entity(repositoryClass: FeaturedItemRepository::class)]
class FeaturedItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['featured:read', 'homepage:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['featured:read', 'homepage:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['featured:read', 'homepage:read'])]
    private ?string $imageSource = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['featured:read', 'homepage:read'])]
    private ?string $imageName = null;

    #[ORM\Column(length: 1000)]
    #[Groups(['featured:read', 'homepage:read'])]
    private ?string $description = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['featured:read', 'homepage:read'])]
    private ?string $link = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['featured:read', 'homepage:read'])]
    private ?float $price = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['featured:read', 'homepage:read'])]
    private ?bool $active = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['featured:read', 'homepage:read'])]
    private ?int $brightness = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['featured:read', 'homepage:read'])]
    private ?int $opacity = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['featured:read', 'homepage:read'])]
    private ?int $contrast = null;

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

    public function setDescription(string $description): static
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

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): static
    {
        $this->price = $price;

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

    public function getContrast(): ?int
    {
        return $this->contrast;
    }

    public function setContrast(?int $contrast): static
    {
        $this->contrast = $contrast;

        return $this;
    }
}
