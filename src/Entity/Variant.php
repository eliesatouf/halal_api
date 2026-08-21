<?php
// src/Entity/Variant.php

namespace App\Entity;
use App\Repository\VariantRepository;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;


#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: 'is_granted("ROLE_ADMIN")'),
        new Patch(security: 'is_granted("ROLE_ADMIN")'),
        new Delete(security: 'is_granted("ROLE_ADMIN")'),

    ],
    normalizationContext: ['groups' => ['variant:read', 'homepage:read']],
    denormalizationContext: ['groups' => ['variant:write', 'dish:write']]
)]

#[ORM\Entity(repositoryClass: VariantRepository::class)]
class Variant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['variant:read', 'dish:read', 'homepage:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Groups(['variant:read', 'variant:write', 'dish:read', 'dish:write', 'homepage:read'])]
    private ?string $size = null;

    #[Assert\Range(
        min: 0.01,
        max: 1000,
        notInRangeMessage: 'Price must be between {{ min }} and {{ max }}'
    )]
    #[Assert\Positive(message: 'Price must be greater than 0')]
    #[ORM\Column]
    #[Groups(['variant:read', 'variant:write' , 'dish:read', 'dish:write', 'homepage:read'])]
    private ?float $price = null;

    #[ORM\ManyToOne(targetEntity: Dish::class, inversedBy: 'variants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['variant:read', 'variant:write'])]
    private ?Dish $dish = null;

    #[ORM\Column]
    #[Groups(['variant:read', 'variant:write'])]
    private int $sortOrder = 0;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['variant:read', 'variant:write', 'dish:read'])]
    private ?string $loyverseItemId = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['variant:read', 'variant:write'])]
    private ?string $loyverseSku = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['variant:read', 'variant:write'])]
    private ?\DateTimeImmutable $syncedAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['variant:read'])]
    private ?string $syncStatus = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['variant:read', 'variant:write'])]
    private ?float $availableStock = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['variant:read'])]
    private ?\DateTimeImmutable $stockLastCheckedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['variant:read', 'variant:write', 'dish:read', 'homepage:read'])]
    private ?string $loyverseVariantId = null;

    #[ORM\Column(nullable: true)]
    private ?bool $availableForSale = null;

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSize(): ?string
    {
        return $this->size;
    }

    public function setSize(string $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getDish(): ?Dish
    {
        return $this->dish;
    }

    public function setDish(?Dish $dish): self
    {
        $this->dish = $dish;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
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

    public function getLoyverseVariantId(): ?string
    {
        return $this->loyverseVariantId;
    }

    public function setLoyverseVariantId(?string $loyverseVariantId): static
    {
        $this->loyverseVariantId = $loyverseVariantId;

        return $this;
    }

    public function isAvailableForSale(): ?bool
    {
        return $this->availableForSale;
    }

    public function setAvailableForSale(?bool $availableForSale): static
    {
        $this->availableForSale = $availableForSale;

        return $this;
    }
}