<?php

namespace App\Entity;

use App\Repository\SubMenuRepository;
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

#[ApiResource(
    operations: [
        new Get( ),
        new GetCollection(filters: ['submenu.MenuName_filter']),
        new Post(),
        new Put(),
        new Delete(security: 'is_granted("ROLE_ADMIN")'),
        new Patch(
            denormalizationContext: ['groups' => ['admin:write']],
            normalizationContext: ['groups' => ['admin:read']],
            securityPostDenormalize: "is_granted('ROLE_ADMIN')",
            validationContext: ['groups' => ['admin:write']],
            security: 'is_granted("ROLE_ADMIN")'
            )
    ]
)]

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: SubMenuRepository::class)]
class SubMenu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['admin:read','admin:write'])]
    private ?int $id = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $type = null;
    
    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $parent = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $category = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageSource = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(nullable: true)]
    private ?int $price = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tag = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(nullable: true)]
    private ?bool $active = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedOn = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $link = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(nullable: true)]
    private ?int $brightness = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(nullable: true)]
    private ?int $opacity = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(nullable: true)]
    private ?int $contrast = null;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getParent(): ?string
    {
        return $this->parent;
    }

    public function setParent(?string $parent): static
    {
        $this->parent = $parent;

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

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;

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

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): static
    {
        $this->price = $price;

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

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): static
    {
        $this->link = $link;

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
