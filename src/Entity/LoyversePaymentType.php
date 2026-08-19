<?php
namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\LoyversePaymentTypeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
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
    ],
    normalizationContext: ['groups' => ['loyverse_payment_types:read']],
    denormalizationContext: ['groups' => ['loyverse_payment_types:write']]
)]


#[ORM\Entity(repositoryClass: LoyversePaymentTypeRepository::class)]
class LoyversePaymentType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['loyverse_payment_types:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['loyverse_payment_types:read', 'loyverse_payment_types:write'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['loyverse_payment_types:read', 'loyverse_payment_types:write'])]
    private ?string $type = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['loyverse_payment_types:read', 'loyverse_payment_types:write'])]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['loyverse_payment_types:read', 'loyverse_payment_types:write'])]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['loyverse_payment_types:read', 'loyverse_payment_types:write'])]
    private ?\DateTimeImmutable $deleted_at = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['loyverse_payment_types:read', 'loyverse_payment_types:write'])]
    private ?string $loyversePaymentTypeId = null;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(?\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deleted_at;
    }

    public function setDeletedAt(?\DateTimeImmutable $deleted_at): static
    {
        $this->deleted_at = $deleted_at;

        return $this;
    }

    public function getLoyversePaymentTypeId(): ?string
    {
        return $this->loyversePaymentTypeId;
    }

    public function setLoyversePaymentTypeId(?string $loyversePaymentTypeId): static
    {
        $this->loyversePaymentTypeId = $loyversePaymentTypeId;

        return $this;
    }
}
