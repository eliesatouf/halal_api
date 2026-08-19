<?php

namespace App\Entity;

use App\Repository\FirstOrderRepository;
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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;

#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(
                security:'is_granted("ROLE_USER") ',
                
                processor: OfferProcessor::class
            ),
        new Put(security:'is_granted("ROLE_ADMIN") '),
        new Delete(security:'is_granted("ROLE_ADMIN")'),
        new Patch(security:'is_granted("ROLE_ADMIN")',
                    processor: OfferProcessor::class)
    ],
    normalizationContext: ['groups' => ['firstOrder:read']],
    denormalizationContext: ['groups' => ['firstOrder:write']],
)]

#[ORM\Entity(repositoryClass: FirstOrderRepository::class)]
class FirstOrder
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['firstorder:read'])]
    #[ORM\OneToOne(inversedBy: 'firstOrder', cascade: ['persist', 'remove'])]
    private ?Customer $customer = null;

    #[Groups(['firstorder:read'])]
    #[ORM\Column(nullable: true)]
    private ?bool $discountApplied = null;

    #[Groups(['firstorder:read'])]
    #[ORM\Column(nullable: true)]
    private ?float $discountValue = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getDiscountApplied(): ?bool
    {
        return $this->discountApplied;
    }

    public function setDiscountApplied(?bool $discountApplied): static
    {
        $this->discountApplied = $discountApplied;

        return $this;
    }

    public function getDiscountValue(): ?float
    {
        return $this->discountValue;
    }

    public function setDiscountValue(?float $discountValue): static
    {
        $this->discountValue = $discountValue;

        return $this;
    }
}
