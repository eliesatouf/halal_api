<?php
namespace App\Entity;
use App\Repository\ModifierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
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
        new Get(),
        new GetCollection(),
        new Post(security: 'is_granted("ROLE_ADMIN")'),
        new Patch(security: 'is_granted("ROLE_ADMIN")'),
        new Put(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['modifier:read']],
    denormalizationContext: ['groups' => ['modifier:write']]
)]

#[ApiFilter(SearchFilter::class, properties: [
    'loyverseModifierId' => 'exact'
])]




#[ORM\Entity(repositoryClass: ModifierRepository::class)]
class Modifier
{
    

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['modifier:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['modifier:read', 'modifier:write'])]
    private ?string $loyverseModifierId = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Groups(['modifier:read', 'modifier:write'])]
    private ?string $name = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['modifier:read', 'modifier:write'])]
    private ?int $position = null;

    #[ORM\Column(type: Types::ARRAY, nullable: true)]
    #[Groups(['modifier:read', 'modifier:write'])]
    private ?array $stores = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['modifier:read', 'modifier:write'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['modifier:read', 'modifier:write'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['modifier:read', 'modifier:write'])]
    private ?\DateTimeImmutable $deletedAt = null;

    /**
     * @var Collection<int, ModifierOption>
     */
    #[ORM\OneToMany(
        mappedBy: 'modifier', 
        targetEntity: ModifierOption::class,
        cascade: ['persist', 'remove'],
        fetch: 'EAGER'
    )]
    #[Groups(['modifier:read', 'modifier:write'])] 
    private Collection $modifierOptions;

    public function __construct()
    {
        $this->modifierOptions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }


    public function getLoyverseModifierId(): ?string
    {
        return $this->loyverseModifierId;
    }


    public function setLoyverseModifierId(?string $loyverseModifierId): static
    {
        $this->loyverseModifierId = $loyverseModifierId;
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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getStores(): ?array
    {
        return $this->stores;
    }

    public function setStores(?array $stores): static
    {
        $this->stores = $stores;

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

    /**
     * @return Collection<int, ModifierOption>
     */
    public function getModifierOptions(): Collection
    {
        return $this->modifierOptions;
    }

    public function addModifierOption(ModifierOption $modifierOption): static
    {
        if (!$this->modifierOptions->contains($modifierOption)) {
            $this->modifierOptions->add($modifierOption);
            $modifierOption->setModifier($this);
        }

        return $this;
    }

    public function removeModifierOption(ModifierOption $modifierOption): static
    {
        if ($this->modifierOptions->removeElement($modifierOption)) {
            // set the owning side to null (unless already changed)
            if ($modifierOption->getModifier() === $this) {
                $modifierOption->setModifier(null);
            }
        }

        return $this;
    }
}
