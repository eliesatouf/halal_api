<?php
// src/Entity/Category.php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;


#[ApiFilter(BooleanFilter::class, properties: ['isActive'])]

#[ORM\Entity]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE', region: 'categories')] 
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post( security: 'is_granted("ROLE_ADMIN")'),
        new Patch( security: 'is_granted("ROLE_ADMIN")'),
        new Delete( security: 'is_granted("ROLE_ADMIN")'),
    ],  
    normalizationContext: ['groups' => ['category:read', 'homepage:read']],
    denormalizationContext: ['groups' => ['category:write']]
)]



class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['category:read', 'menu_item:read' , 'dish:read','homepage:read', 'homepage:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['category:read', 'category:write', 'menu_item:read'  , 'dish:read', 'dish:write' ,'homepage:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['category:read', 'category:write','homepage:read'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['category:read', 'category:write','homepage:read'])]
    private int $sortOrder = 0;

    #[ORM\Column]
    #[Groups(['category:read', 'category:write','homepage:read'])]
    private bool $isActive = true;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Dish::class)]
    #[Groups(['category:read','homepage:read'])]
    private Collection $dishes;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $loyverseCategoryId = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $syncedAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $syncStatus = null;

    public function __construct()
    {
        $this->dishes = new ArrayCollection();
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        $this->slug = $slug === '' ? null : $slug;
        return $this->slug;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
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

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    /**
     * @return Collection<int, Dish>
     */
    public function getDishes(): Collection
    {
        return $this->dishes;
    }

    public function addDish(Dish $dishe): self
    {
        if (!$this->dishes->contains($dishe)) {
            $this->dishes->add($dishe);
            $dishe->setCategory($this);
        }
        return $this;
    }

    public function removeDish(Dish $dishe): self
    {
        if ($this->dishes->removeElement($dishe)) {
            if ($dishe->getCategory() === $this) {
                $dishe->setCategory(null);
            }
        }
        return $this;
    }

    public function getLoyverseCategoryId(): ?string
    {
        return $this->loyverseCategoryId;
    }

    public function setLoyverseCategoryId(?string $loyverseCategoryId): static
    {
        $this->loyverseCategoryId = $loyverseCategoryId;

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

}