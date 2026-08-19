<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
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
        new Get(security:'is_granted("ROLE_USER") '),
        new GetCollection(security:'is_granted("ROLE_USER") '),
        new Post(security: 'is_granted("ROLE_USER")',
            denormalizationContext: ['groups' => ['order_item:write']]
        ),
        new Put(security:'is_granted("ROLE_USER")'),
        new Delete(security:'is_granted("ROLE_USER")'),
        new Patch(security: 'is_granted("ROLE_USER")',
            denormalizationContext: ['groups' => ['order_item:write']]
        )
    ],
    normalizationContext: ['groups' => ['order_item:read'],
        'enable_max_depth' => true
    ]
)]

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
class OrderItem
{
    #[Groups(['order:read', 'order_item:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['order:write','order:read','order_item:read', 'order_item:write'])]
    #[ORM\Column(nullable: true)]
    private ?int $quantity = null;

    #[Groups(['order_item:read','order:read'])] 
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $unitPrice = null;

    #[Groups(['order_item:read', 'order_item:write'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $specialInstructions = null;

    #[Groups(['order_item:read','order:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $totalPrice = null;

    //-- BOGO --//

    #[Groups(['order_item:read'])]
    #[ORM\Column(nullable: true)]
    private ?bool $isBogoFreeItem = null;

    #[ORM\ManyToOne(targetEntity: OrderItem::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['order_item:read'])] 
    private ?OrderItem $bogoParentItem = null;

    #[Groups(['order_item:read'])] 
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'bogoParentItem')]
    private Collection $bogoFreeItems;

    #[Groups(['order_item:read'])]
    #[ORM\Column(nullable: true)]
    private ?float $customizedPrice = null;

    public function getCustomizedPrice(): ?float
    {
        return $this->customizedPrice;
    }

    public function setCustomizedPrice(?float $customizedPrice): static
    {
        $this->customizedPrice = $customizedPrice;
        return $this;
    }

    public function __construct()
    {
        $this->dish = null;
        $this->bogoFreeItems = new ArrayCollection();
        $this->isBogoFreeItem = false;
        //$this->dish = null; 
    }

    // Add the getters and setters
    public function getIsBogoFreeItem(): ?bool
    {
        return $this->isBogoFreeItem;
    }

    public function setIsBogoFreeItem(?bool $isBogoFreeItem): static
    {
        $this->isBogoFreeItem = $isBogoFreeItem;
        return $this;
    }

    public function getBogoParentItem(): ?OrderItem
    {
        return $this->bogoParentItem;
    }

    public function setBogoParentItem(?OrderItem $bogoParentItem): static
    {
        $this->bogoParentItem = $bogoParentItem;
        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getBogoFreeItems(): Collection
    {
        return $this->bogoFreeItems;
    }

    public function addBogoFreeItem(OrderItem $bogoFreeItem): static
    {
        if (!$this->bogoFreeItems->contains($bogoFreeItem)) {
            $this->bogoFreeItems->add($bogoFreeItem);
            $bogoFreeItem->setBogoParentItem($this);
        }
        return $this;
    }

    public function removeBogoFreeItem(OrderItem $bogoFreeItem): static
    {
        if ($this->bogoFreeItems->removeElement($bogoFreeItem)) {
            if ($bogoFreeItem->getBogoParentItem() === $this) {
                $bogoFreeItem->setBogoParentItem(null);
            }
        }
        return $this;
    }

    // END BOGO --//
    #[Groups(['order:write','order_item:write'])]
    #[ORM\ManyToOne(targetEntity: Order::class,inversedBy: 'orderItem')]
    private ?Order $order = null;


    #[Groups(['order:write','order:read','order_item:read', 'order_item:write'])]
    #[ORM\ManyToOne(targetEntity: Dish::class)]
    private ?Dish $dish = null;


    public function getAppliedDiscountValue(): ?float
    {
        if (!$this->dish) return null;
        
        $discount = $this->dish->getActiveOffer()?->getDiscount();
        if (!$discount) return null;

        // Use dish price for discount calculation
        $dishPrice = (float) $this->dish->getPrice();
        
        if ($discount->getDiscountType() === 'percentage') {
            return $dishPrice * ((float) $discount->getValue() / 100) * $this->quantity;
        } else {
            return (float) $discount->getValue() * $this->quantity;
        }
    }

    // Optional: Add this if you need discount details
    #[Groups(['order_item:read'])]
    public function getAppliedDiscount(): ?int
    {
        // Handle dish discounts
        if ($this->dish) {
            $discount = $this->dish->getActiveOffer()?->getDiscount();
            if (!$discount) return null;

            // CHECK MIN ORDER AMOUNT
            $minOrderAmount = (float) ($discount->getMinOrderAmount() ?? 0);
            if ($minOrderAmount > 0) {
                $order = $this->getOrder();
                if ($order && (float) $order->getSubtotal() < $minOrderAmount) {
                    return 0; // Discount not applicable
                }
            }

            $dishPrice = (float) $this->dish->getPrice();
            
            if ($discount->getDiscountType() === 'percentage') {
                return $dishPrice * ((float) $discount->getValue() / 100) * $this->quantity;
            } else {
                return (float) $discount->getValue() * $this->quantity;
            }
        }
        
        // Handle bundle discounts
        if ($this->bundle) {
            // Add bundle discount calculation here if needed
            return 0;
        }
        
        return null;
    }

    public function calculateDiscountValue(): ?float
    {
        // Handle bundle discounts
        if ($this->bundle) {
            return 0;
        }
        
        // Handle loyalty rewards first (they override other discounts)
        if ($this->dish && $this->appliedReward) {
            $reward = $this->getAppliedReward();
            if ($reward) {
                $milestone = $reward->getMilestone();
                $rewardType = $milestone->getRewardType();
                $correctPrice = $this->getCorrectPrice();
                $itemSubtotal = ($correctPrice * $this->quantity) + $this->getModifierTotal();

                if ($rewardType == 'free_service') {
                    return $itemSubtotal;
                } elseif ($rewardType == 'percentage_discount') {
                    $mileStoneDiscount = $milestone->getRewardValue();
                    return $itemSubtotal * ($mileStoneDiscount / 100);
                }
            }
        }
        
        // Handle dish discounts
        if ($this->dish) {
            $offer = $this->dish->getActiveOffer();
            
            // No active offer
            if (!$offer || !$offer->isActive()) {
                return null;
            }

            // BOGO offers are handled by strategy, not discount calculation
            if ($offer->getIsBogo() === true) {
                return 0;
            }

            // Process regular discounts
            $discount = $offer->getDiscount();
            if (!$discount) {
                return null;
            }

            $discountType = $discount->getDiscountType();
            $discountValue = (float) $discount->getValue();
            $correctPrice = $this->getCorrectPrice(); 
            $itemSubtotal = ($correctPrice * $this->quantity) + $this->getModifierTotal();
            
            if ($discountType === 'percentage') {
                return $itemSubtotal * ($discountValue / 100);
            } elseif ($discountType === 'fixed_amount') {
                return $discountValue * $this->quantity;
            }
        }
        
        return 0;
    }


    /**
     * Get the correct price for this item (variant price if variant exists, otherwise dish price)
     */
    private function getCorrectPrice(): float
    {
        $variantId = $this->getVariantId();
        if ($variantId && $this->dish) {
            foreach ($this->dish->getVariants() as $variant) {
                if ($variant->getId() == $variantId) {
                    return (float) $variant->getPrice();
                }
            }
        }
        
        return (float) $this->dish->getPrice();
    }


    // public function calculateDiscountValue(): ?float
    // {
        
    //     // Handle bundle discounts (if bundles can have discounts)
    //     if ($this->bundle) {
    //         // Add bundle discount logic here if needed
    //         return 0;
    //     }
        
    //     // Handle dish discounts
    //     if ($this->dish && !$this->appliedReward) {

    //         $offer = $this->dish->getActiveOffer();
    //         if (!$offer || !$offer->isActive()) return null;

    //         // If the offer is a BOGO type, its logic is handled by the strategy
    //         if ($offer->getIsBogo() === true) {
    //             return 0;
    //         }


    //     } elseif ($this->dish && $this->appliedReward) { //-- if loyalty reward applicable
    //         // get the reward 
    //         $reward = $this->getAppliedReward();

    //         if ($reward) {
    //             // get milestone
    //             $milestone = $reward->getMilestone();
    //             $rewardType = $milestone->getRewardType();

    //             if ($rewardType == 'free_service') {
    //                 // For free service, discount should be the item subtotal
    //                 $itemSubtotal = $this->calculateItemSubtotal();
    //                 return $itemSubtotal;
    //             } elseif ($rewardType == 'percentage_discount') {
    //                 $mileStoneDiscount = $milestone->getRewardValue();
    //                 $itemSubtotal = $this->calculateItemSubtotal();
    //                 return $itemSubtotal * ($mileStoneDiscount / 100);
    //             }
    //         }
    //     }

    //     $discount = $offer->getDiscount();
    //     if (!$discount) return null;

    //     $discountType = $discount->getDiscountType();
    //     $discountValue = (float) $discount->getValue();
        
    //     // Calculate discount based on item subtotal (not just base price)
    //     $itemSubtotal = $this->calculateItemSubtotal();
        
    //     if ($discountType === 'percentage') {
    //         return $itemSubtotal * ($discountValue / 100);
    //     } elseif ($discountType === 'fixed_amount') {
    //         return $discountValue * $this->quantity;
    //     }
        
        
        
    //     return 0;
    // }

    /**
     * Calculate item subtotal (base price + modifiers)
     * This should be included in the Order's subtotal
     */
    public function calculateItemSubtotal(): float
    {
        if ($this->isBogoFreeItem) {
            return 0;
        }

        if ($this->dish) {
            // Use the correct price (variant if available)
            $correctPrice = $this->getCorrectPrice();
            $basePrice = $correctPrice * $this->quantity;
            $modifierTotal = $this->getModifierTotal();
            return $basePrice + $modifierTotal;
        }
        
        if ($this->bundle) {
            $bundlePrice = (float) $this->bundle->getPrice();
            return $bundlePrice * $this->quantity;
        }
        
        return 0;
    }

    // Expose item subtotal in API
    #[Groups(['order_item:read'])]
    public function getItemSubtotal(): float
    {
        return $this->calculateItemSubtotal();
    }

    /**
     * Calculate total modifier cost for this item
     */
    public function getModifierTotal(): float
    {
        $total = 0;
        if ($this->getLineModifiers() && count($this->getLineModifiers()) > 0) {
            foreach ($this->getLineModifiers() as $modifier) {
                // Each modifier price applies to the quantity
                $total += (float) $modifier['price'] * $this->quantity;
            }
        }
        return $total;
    }


    /**
     * Calculate total price for this order item (for order total calculation)
     */
    public function calculateTotalPrice(): float
    {
        if ($this->isBogoFreeItem) {
            return 0;
        }

        // Handle dish items
        if ($this->dish) {
            // Base price without modifiers
            $basePrice = (float) $this->getUnitPrice() * $this->quantity;
            
            // Add modifier costs
            $modifierTotal = $this->getModifierTotal();
            $itemSubtotal = $basePrice + $modifierTotal;
            
            // Apply discount if any (discount should apply to itemSubtotal)
            $discount = $this->calculateDiscountValue() ?? 0;
            
            return max(0, $itemSubtotal - $discount);
        }
        
        // Handle bundle items
        if ($this->bundle) {
            $bundlePrice = (float) $this->bundle->getPrice();
            return $bundlePrice * $this->quantity;
        }
        
        return 0;
    }


    /**
     * Calculate discount value for this order item (for order total calculation)
     */
    // public function calculateDiscountValue(): ?float
    // {
    //     if (!$this->dish) return null;
    
    //     $discount = $this->dish->getActiveOffer()?->getDiscount();
    //     if (!$discount) return null;

    //     // CHECK MIN ORDER AMOUNT
    //     $minOrderAmount = (float) ($discount->getMinOrderAmount() ?? 0);
    //     if ($minOrderAmount > 0) {
    //         $order = $this->getOrder();
    //         if ($order && (float) $order->getSubtotal() < $minOrderAmount) {
    //             return 0; // Discount not applicable
    //         }
    //     }

    //     $discountType = $discount->getDiscountType();
    //     $discountValue = (float) $discount->getValue();
    //     $dishPrice = (float) $this->dish->getPrice();

    //     if ($discountType === 'percentage') {
    //         return $dishPrice * ($discountValue / 100) * $this->quantity;
    //     } elseif ($discountType === 'fixed_amount') {
    //         return $discountValue * $this->quantity;
    //     }
        
    //     return 0;
    // }

    /**
     * Check if this item provides free shipping
     */
    public function providesFreeShipping(): bool
    {
        // Handle dish free shipping
        if ($this->dish) {
            $offer = $this->dish->getActiveOffer();
            if (!$offer) return false;
            
            return $offer->getFreeShipping() === true;
        }
        
        // Handle bundle free shipping (if bundles can offer free shipping)
        if ($this->bundle) {
            // Add bundle free shipping logic here if needed
            return false;
        }
        
        return false;
    }


    // set price from dish automatically
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function setPriceFromDish(): void
    {
        if (!$this->dish) {
            return;
        }

        // Use the correct price (variant if available)
        $price = $this->getCorrectPrice();

        // Add customization costs
        if ($this->customizations) {
            $price = $this->addCustomizationCosts($price);
        }
        
        // Set the unit price
        $this->unitPrice = (string) $price;

        // Handle bundle items
        if ($this->bundle) {
            $this->unitPrice = (string) $this->bundle->getPrice();
        }
        
        // Ensure BOGO free items have price 0
        if ($this->isBogoFreeItem) {
            $this->unitPrice = '0';
            $this->totalPrice = '0';
            return;
        }
        
        // Calculate base total
        $baseTotal = (float) $this->unitPrice * $this->quantity;
        $modifierTotal = $this->getModifierTotal();
        $itemSubtotal = $baseTotal + $modifierTotal;
        
        // Calculate discount and final total
        $discount = $this->calculateDiscountValue() ?? 0;
        $finalTotal = max(0, $itemSubtotal - $discount);
        
        $this->totalPrice = (string) $finalTotal;
        $this->getNameOfVariant();
    }



    private function addCustomizationCosts(float $basePrice): float
    {
        $total = $basePrice;
        
        // Get customization prices from Dish entity (add this field)
        $customPrices = $this->dish->getCustomizationPrices() ?? [];
        
        // Milk adjustment
        if (isset($this->customizations['milk']) && 
            isset($customPrices['milk'][$this->customizations['milk']])) {
            $total += $customPrices['milk'][$this->customizations['milk']];
        }
        
        // Sweetness (usually only 'extra' costs extra)
        if (isset($this->customizations['sweetness']) && 
            $this->customizations['sweetness'] === 'extra' &&
            isset($customPrices['sweetness']['extra'])) {
            $total += $customPrices['sweetness']['extra'];
        }
        
        // Extras
        foreach ($this->customizations['extras'] ?? [] as $extra) {
            if (isset($customPrices['extras'][$extra])) {
                $total += $customPrices['extras'][$extra];
            }
        }
        
        return $total;
    }

    private function getNameOfVariant()
    {
        
        $variantId = $this->getVariantId();

        if ($variantId) {
            foreach ($this->dish->getVariants()->toArray() as $variant) {
                if ($variant->getId() == $variantId) {
                    $name = $variant->getSize();
                    $this->setVariantName($name);
                }
            }
        }
    }


    #[Groups(['order_item:read','order_item:write','order:write', 'order:read'])]
    #[ORM\ManyToOne(targetEntity: Bundle::class)]
    private ?Bundle $bundle = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['order_item:read','order_item:write', 'order:read', 'order:write'])]
    private ?int $variantId = null;

    #[ORM\Column(nullable: true)]
    private ?array $customizations = null;

    #[Groups(['order_item:read','order_item:write'])]
    public function getBundle(): ?Bundle
    {
        return $this->bundle;
    }

    #[Groups(['order_item:read','order_item:write'])]
    public function setBundle(?Bundle $bundle): static
    {
        $this->bundle = $bundle;
        return $this;
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    #[Groups(['order_item:read'])] // Calculated field
    public function getTotalPrice(): float
    {
        return $this->calculateTotalPrice();
    }

    public function setTotalPrice(?string $totalPrice): static
    {
        $this->totalPrice = $totalPrice;

        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;
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

    public function getVariantId(): ?int
    {
        return $this->variantId;
    }

    public function setVariantId(?int $variantId): static
    {
        $this->variantId = $variantId;

        return $this;
    }

    public function getCustomizations(): ?array
    {
        return $this->customizations;
    }

    public function setCustomizations(?array $customizations): static
    {
        $this->customizations = $customizations;

        return $this;
    }

    #[ORM\ManyToOne(targetEntity: LoyaltyReward::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['order_item:read', 'order_item:write', 'order:read', 'order:write'])]
    private ?LoyaltyReward $appliedReward = null;

    #[ORM\Column(type: Types::ARRAY, nullable: true)]
    #[Groups(['order_item:read', 'order_item:write', 'order:read', 'order:write'])]
    private ?array $lineModifiers = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['order_item:read', 'order_item:write', 'order:read', 'order:write'])]
    private ?string $variantName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['order_item:read', 'order_item:write', 'order:read', 'order:write'])]
    private ?string $accrued = null;


    public function getAppliedReward(): ?LoyaltyReward { return $this->appliedReward; }
    public function setAppliedReward(?LoyaltyReward $appliedReward): static { 
        $this->appliedReward = $appliedReward; 
        return $this; 
    }

    public function getLineModifiers(): ?array
    {
        return $this->lineModifiers;
    }

    public function setLineModifiers(?array $lineModifiers): static
    {
        $this->lineModifiers = $lineModifiers;

        return $this;
    }

    public function getVariantName(): ?string
    {
        return $this->variantName;
    }

    public function setVariantName(?string $variantName): static
    {
        $this->variantName = $variantName;

        return $this;
    }

    public function getAccrued(): ?string
    {
        return $this->accrued;
    }

    public function setAccrued(?string $accrued): static
    {
        $this->accrued = $accrued;

        return $this;
    }


}
