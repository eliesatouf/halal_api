<?php
// src/Entity/BundleItem.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use ApiPlatform\Metadata\ApiProperty;
use App\Repository\BundleItemRepository;

#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: 'is_granted("ROLE_ADMIN")', 
            denormalizationContext: ['groups' => ['bundle_item:write']]),
        new Put(security: 'is_granted("ROLE_ADMIN")'),
        new Patch(security: 'is_granted("ROLE_ADMIN")'),
        new Delete(security: 'is_granted("ROLE_ADMIN")'),
    ],
    normalizationContext: [
        'groups' => ['bundle_item:read'],
        'enable_max_depth' => true
    ],
    // Add denormalization context for embedded handling
    denormalizationContext: [
        'groups' => ['bundle_item:write'],
        'allow_extra_attributes' => true
    ]
   
)]
#[ORM\Entity]
class BundleItem
{
    #[Groups(['bundle_item:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['bundle_item:write','bundle_item:read','bundle:read','bundle:write'])]
    #[ORM\ManyToOne(targetEntity: Dish::class)]
    private ?Dish $dish = null;

    #[Groups(['bundle_item:write','bundle_item:read','bundle:read','bundle:write'])]
    #[ORM\Column]
    private ?int $quantity = 1;

    #[Groups(['bundle_item:write'])]
    #[ORM\ManyToOne(targetEntity: Bundle::class,inversedBy: 'bundleItems')]
    #[ApiProperty(iris: ['/api/dishes/{id}'])]
    private ?Bundle $bundle = null;

    public function getId(): ?int 
    { 
        return $this->id; 
    }
    public function getBundle(): ?Bundle 
    { 
        return $this->bundle; 
    }

    public function setBundle(?Bundle $bundle): static
    { 
        $this->bundle = $bundle;
         return $this; 
    }

    public function getDish(): ?Dish
    { 
        return $this->dish; 
    }

    public function setDish(?Dish $dish): static
    { 
        $this->dish = $dish;
         return $this;
    }

    public function getQuantity(): ?int 
    { 
        return $this->quantity; 
    }
    
    public function setQuantity(int $quantity): static
    { 
        $this->quantity = $quantity; return $this;
    }
}