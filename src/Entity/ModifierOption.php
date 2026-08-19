<?php

namespace App\Entity;

use App\Repository\ModifierOptionRepository;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted("ROLE_USER")'),
        new GetCollection(),
        new Post(security: 'is_granted("ROLE_USER")'),
        new Put(security: 'is_granted("ROLE_USER")'),
        new Delete(security: 'is_granted("ROLE_USER")'),
        new Patch(security: 'is_granted("ROLE_USER")'),
    ],
    normalizationContext: [
        'groups' => ['modifier_option:read', 'homepage:read'],
        'enable_max_depth' => true
    ],
    denormalizationContext: ['groups' => ['modifier_option:write']]  // Added global
)]

#[ApiFilter(SearchFilter::class, properties: [
    'loyverseModifierOptionId' => 'exact'
])]

#[ORM\Entity(repositoryClass: ModifierOptionRepository::class)]
class ModifierOption
{
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['modifier_option:read', 'modifier_option:write', 'modifier:read', 'homepage:read'])]
    private ?string $loyverseModifierOptionId = null;


    #[ORM\Column(length: 40, nullable: true)]
    #[Groups(['modifier_option:read', 'modifier_option:write', 'modifier:read', 'homepage:read'])]
    private ?string $name = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['modifier_option:read', 'modifier_option:write', 'modifier:read', 'homepage:read'])]
    private ?float $price = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['modifier_option:read', 'modifier_option:write', 'modifier:read', 'homepage:read'])]
    private ?int $position = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['modifier_option:read', 'modifier:read', 'homepage:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['modifier_option:read', 'modifier:read', 'homepage:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['modifier_option:read', 'modifier:read', 'homepage:read'])]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\ManyToOne(inversedBy: 'modifierOptions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['modifier_option:read', 'modifier_option:write', 'homepage:read'])]  // Added ':write'
    private ?Modifier $modifier = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLoyverseModifierOptionId(): ?string
    {
        return $this->loyverseModifierOptionId;
    }


    public function setLoyverseModifierOptionId(?string $loyverseModifierOptionId): static
    {
        $this->loyverseModifierOptionId = $loyverseModifierOptionId;
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

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getModifier(): ?Modifier
    {
        return $this->modifier;
    }

    public function setModifier(?Modifier $modifier): static
    {
        $this->modifier = $modifier;

        return $this;
    }

}
