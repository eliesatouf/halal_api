<?php

namespace App\Entity;

use App\Repository\MainMenuEcRepository;
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

#[ApiResource(
    operations: [
        new Get(filters: ['submenu.MenuName_filter'] ),
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

#[ORM\Entity(repositoryClass: MainMenuEcRepository::class)]
#[ORM\HasLifecycleCallbacks]
class MainMenuEc
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['admin:read','admin:write'])]
    private ?int $id = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(length: 800, nullable: true)]
    private ?string $description = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $category = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $link = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageSource = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bgColor = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $textColor = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(nullable: true)]
    private ?bool $active = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedOn = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(nullable: true)]
    private ?int $price = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(nullable: true)]
    private ?string $tag = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tabName = null;

    #[Groups(['admin:read','admin:write'])]
    #[ORM\Column(nullable: true)]
    private ?bool $hasChild = null;

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

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;

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

    public function getImageSource(): ?string
    {
        return $this->imageSource;
    }

    public function setImageSource(?string $imageSource): static
    {
        $this->imageSource = $imageSource;

        return $this;
    }

    public function getBgColor(): ?string
    {
        return $this->bgColor;
    }

    public function setBgColor(?string $bgColor): static
    {
        $this->bgColor = $bgColor;

        return $this;
    }

    public function getTextColor(): ?string
    {
        return $this->textColor;
    }

    public function setTextColor(?string $textColor): static
    {
        $this->textColor = $textColor;

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

    public function getTabName(): ?string
    {
        return $this->tabName;
    }

    public function setTabName(?string $tabName): static
    {
        $this->tabName = $tabName;

        return $this;
    }

    public function getHasChild(): ?bool
    {
        return $this->hasChild;
    }

    public function setHasChild(?bool $hasChild): static
    {
        $this->hasChild = $hasChild;

        return $this;
    }


}
