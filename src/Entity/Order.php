<?php
namespace App\Entity;
use App\Repository\OrderRepository;
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
use App\State\OfferProcessor;
use App\Repository\FirstOrderRepository;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Serializer\Filter\PropertyFilter;

#[ApiResource(
    // mercure: [
    //     'topics' => [
    //         '/api/orders/{id}',  // Individual order updates
    //         '/tidybubbles_orders'            // Custom topic for ALL orders
    //     ]
    // ],
    operations: [
        new Get(security: 'is_granted("ROLE_USER")'),
        new GetCollection(security: 'is_granted("ROLE_ADMIN")'),
        new Post(
            security: 'is_granted("ROLE_USER")',
            denormalizationContext: ['groups' => ['order:write']],
            processor: OfferProcessor::class,
            mercure: false,  // ← DISABLE automatic Mercure for POST only
        ),
        new Put(security: 'is_granted("ROLE_ADMIN")'),
        new Delete(security: 'is_granted("ROLE_ADMIN")'),
        new Patch(
            security: 'is_granted("ROLE_ADMIN")',
            processor: OfferProcessor::class,
            denormalizationContext: ['groups' => ['order:write']],
            mercure: [  // Add Mercure only for PATCH
                'topics' => [
                    '/api/orders/{id}',
                    '/tidybubbles_orders'
                ]
            ]
        )
    ],
    normalizationContext: ['groups' => ['order:read'], 'enable_max_depth' => true],
    filters: [App\Filter\CustomerOrderFilter::class],
)]

#[ApiProperty(
    readable: true,
    writable: true,
    readableLink: false,
    writableLink: false
)]


#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[ApiFilter(DateFilter::class, properties: ['createdOn' => DateFilter::EXCLUDE_NULL])]

class Order
{
    #[Groups(['order:read', 'order:write'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['order:read', 'order:write'])]
    #[ORM\Column]
    private ?string $deliveryAddress;

    #[Groups(['order:read', 'order:write'])]
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $orderStatus = null;

    #[Groups(['order:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $subtotal = null;

    #[Groups(['order:read', 'order:write'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $deliveryFee = null;

    #[Groups(['order:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $totalDiscount = null;

    #[Groups(['order:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $grandTotal = null;

    #[Groups(['order:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $taxAmount = null;

    #[Groups(['order:read', 'order:write'])]
    #[ORM\ManyToOne(inversedBy: 'order')]
    #[MaxDepth(1)]
    private ?Customer $customer = null;

    public function getTaxAmount(): ?string
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(?string $taxAmount): static
    {
        $this->taxAmount = $taxAmount;
        return $this;
    }


    /**
     * @var Collection<int, OrderItem>
     */
    #[Groups(['order:read', 'order:write'])]
    #[ORM\OneToMany(
        targetEntity: OrderItem::class,
        mappedBy: 'order',
        cascade: ['persist', 'remove'],
        fetch: 'EAGER'
    )]
    private Collection $orderItem;

    #[Groups(['order:read'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $orderNumber = null;

    #[Groups(['order:read', 'order:write'])]
    #[ORM\Column(nullable: true)]
    private ?\DateTime $orderDate = null;

    #[Groups(['order:read', 'order:write'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $orderChannel = null;

    #[Groups(['order:read', 'order:write'])]
    #[ORM\Column(nullable: true)]
    private ?\DateTime $createdOn = null;

    #[Groups(['order:read', 'order:write'])]
    #[ORM\Column(nullable: true)]
    private ?\DateTime $updatedOn = null;

    #[Groups(['order:read', 'order:write'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $loyverseReceiptId = null;

    
    #[Groups(['order:read'])]
    #[ORM\ManyToOne(targetEntity: FirstOrder::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?FirstOrder $appliedFirstOrderDiscount = null;

    public function getAppliedFirstOrderDiscount(): ?FirstOrder
    {
        return $this->appliedFirstOrderDiscount;
    }

    public function setAppliedFirstOrderDiscount(?FirstOrder $firstOrder): static
    {
        $this->appliedFirstOrderDiscount = $firstOrder;
        return $this;
    }


    public function getLoyverseReceiptId(): ?string
    {
        return $this->loyverseReceiptId;
    }

    public function setLoyverseReceiptId(?string $loyverseReceiptId): static
    {
        $this->loyverseReceiptId = $loyverseReceiptId;
        return $this;
    }

    public function __construct()
    {

        $this->orderItem = new ArrayCollection();
        $this->orderDiscount = new ArrayCollection();

    }

    #[ORM\PrePersist]
    public function generateOrderNumber(): void
    {
        if (empty($this->orderNumber)) {
            // Shorter format for Loyverse compatibility (max 20 chars)
            $timestamp = date('Ymd'); // e.g., 20251224-122503
            $random = rand(100, 999); // 3 digits
            $this->orderNumber = 'O-' . $timestamp . '-' . $random; // 20 chars max
        }
    }

    public function calculateDeliveryFee(): float
    {
        // Check for free shipping conditions
        if ($this->getHasFreeShipping()) {
            return 0;
        }
        
        // Check free shipping threshold based on subtotal (which includes modifiers)
        $freeShippingThreshold = 50.00;
        if ((float) $this->subtotal >= $freeShippingThreshold) {
            return 0;
        }
        
        // Check if any item provides free shipping
        foreach ($this->orderItem as $item) {
            if ($item->providesFreeShipping()) {
                return 0;
            }
        }
        
        return 0; // Default delivery fee
    }


    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function calculateOrderTotals(): void
    {
        $subtotal = 0;
        $totalDiscount = 0;
        
        foreach ($this->orderItem as $item) {
            // Item subtotal includes modifiers
            $itemSubtotal = $item->calculateItemSubtotal();
            $subtotal += $itemSubtotal;
            
            // Item total after discount
            $itemTotal = $item->calculateTotalPrice();
            
            // Calculate discount for this item
            $itemDiscount = $itemSubtotal - $itemTotal;
            $totalDiscount += $itemDiscount;
        }
        
        $this->subtotal = (string) $subtotal;
        $this->totalDiscount = (string) $totalDiscount; // Changed from discountAmount
        
        // Calculate tax on subtotal (subtotal includes modifiers)
        $taxRate = 0.00; // Get from your tax configuration
        $taxAmount = $subtotal * $taxRate;
        $this->taxAmount = (string) $taxAmount;
        
        // Calculate delivery fee
        $deliveryFee = $this->calculateDeliveryFee();
        $this->deliveryFee = (string) $deliveryFee;
        
        // Grand total: subtotal + tax + delivery fee
        $grandTotal = $subtotal + $taxAmount + $deliveryFee;
        $this->grandTotal = (string) $grandTotal;
    }

    public function calculateShipping(): float
    {
        // Free shipping threshold (example: $50)
        $freeShippingThreshold = 50.00;
        
        // Use subtotal which includes modifiers
        if ((float) $this->subtotal >= $freeShippingThreshold) {
            return 0;
        }
        
        // Check if any item provides free shipping
        foreach ($this->orderItem as $item) {
            if ($item->providesFreeShipping()) {
                return 0;
            }
        }
        
        return 0; // Default shipping rate
    }

    //-- BOGO --//
    public function addItem(OrderItem $item): static
    {
        if (!$this->orderItem->contains($item)) {
            $this->orderItem->add($item);
            $item->setOrder($this);
        }
        return $this;
    }

    #[Groups(['order:read', 'order:write'])]
    public function getBogoItems(): array
    {
        $bogoItems = [];
        foreach ($this->getOrderItem() as $item) { // FIXED: Use getOrderItem()
            if ($item->getIsBogoFreeItem()) {
                $bogoItems[] = [
                    'id' => $item->getId(),
                    'dish' => $item->getDish()?->getName(),
                    'parentItem' => $item->getBogoParentItem()?->getId()
                ];
            }
        }
        return $bogoItems;
    }

    //-- END BOGO -- //

    public function updateOrderItems(): void
    {
        foreach ($this->orderItem as $item) {
            // Ensure each item knows it belongs to this order
            if ($item->getOrder() !== $this) {
                $item->setOrder($this);
            }
        }
    }

    public function onPrePersist(): void
    {
        // Set timestamps
        $this->createdOn = new \DateTime();
        $this->updatedOn = new \DateTime();

        // Generate order number if not set
        if (empty($this->orderNumber)) {
            $this->orderNumber = 'ORD-' . time() . '-' . rand(1000, 9999);
        }

        // Set order date if not set
        if (empty($this->orderDate)) {
            $this->orderDate = new \DateTime();
        }

        // Calculate totals
        //$this->calculateTotals();
    }

    public function onPreUpdate(): void
    {
        $this->updatedOn = new \DateTime();
        $this->calculateTotals(); // Recalculate on update too
    }

    /**
 * Check if first order discount has been applied
 */
public function isFirstOrderDiscountApplied(): bool
{
    return $this->appliedFirstOrderDiscount !== null && 
           $this->appliedFirstOrderDiscount->getDiscountApplied() === true;
}

/**
 * Get first order discount amount
 */
public function getFirstOrderDiscountAmount(): float
{
    if (!$this->isFirstOrderDiscountApplied()) {
        return 0.;
    }
    
    return (float) $this->appliedFirstOrderDiscount->getDiscountValue();
}


    
    public function calculateTotals(): void
    {
        $subtotal = 0;
        $totalDiscount = 0;
        $hasFreeShipping = false;

        foreach ($this->orderItem as $item) {
            if ($item->getIsBogoFreeItem()) {
                $originalPrice = $item->calculateItemSubtotal();
                $totalDiscount += $originalPrice;
                continue;
            }

            // For regular items, use the final price after discounts
            $itemFinalPrice = $item->calculateTotalPrice(); // This is 144
            $subtotal += $itemFinalPrice; // Add 144 to subtotal

            if ($item->getDish()) {
                $itemOriginalPrice = $item->calculateItemSubtotal(); // This is 160
                $itemDiscount = $itemOriginalPrice - $itemFinalPrice; // 16
                $totalDiscount += $itemDiscount;

                if ($item->providesFreeShipping()) {
                    $hasFreeShipping = true;
                }
            } elseif ($item->getBundle()) {
                $bundle = $item->getBundle();
                $bundleDiscount = $bundle->getSavings() * $item->getQuantity();
                $totalDiscount += $bundleDiscount;
            }
        }

        // Apply first order discount if applicable
        if ($this->isFirstOrderDiscountApplied()) {
            $firstOrderDiscountAmount = $this->getFirstOrderDiscountAmount();
            $subtotal -= $firstOrderDiscountAmount;
            $totalDiscount += $firstOrderDiscountAmount;
        }

        $this->subtotal = (string) round($subtotal, 2);      // Should now be 144
        $this->totalDiscount = (string) round($totalDiscount, 2); // Should now be 16
        
        // Calculate delivery fee
        $deliveryFee = $hasFreeShipping ? 0 : $this->calculateDeliveryFee();
        $this->deliveryFee = (string) $deliveryFee;

        // Calculate tax
        $taxRate = 0;
        $taxableAmount = (float) $this->subtotal;
        $taxAmount = $taxableAmount * $taxRate;
        $this->taxAmount = (string) round($taxAmount, 2);

        // Calculate grand total
        $grandTotal = max(0, (float) $this->subtotal + $taxAmount + $deliveryFee);
        $this->grandTotal = (string) round($grandTotal, 2);

        $this->hasFreeShipping = $hasFreeShipping;
    }

    #[Groups(['order:read'])]
    public function getDiscountStatus(): array
    {
        $status = [];
        $baseSubtotal = 0;

        // Calculate base subtotal first (original prices including modifiers)
        foreach ($this->orderItem as $item) {
            if ($item->getIsBogoFreeItem())
                continue;

            // Use calculateItemSubtotal() which includes modifiers
            $baseSubtotal += $item->calculateItemSubtotal();
        }

        // Check each item's discount eligibility based on base subtotal
        foreach ($this->orderItem as $item) {
            $deliveryFee = $item->getDish()?->getActiveOffer()?->getDeliveryFee();
            if ($deliveryFee > $this->deliveryFee) {
                $this->setDeliveryFee($deliveryFee);
            }

            $bundleOffers = $item->getBundle()?->getOffers();
            if ($bundleOffers) {
                foreach ($bundleOffers as $offer) {
                    $deliveryFee = $offer->getDeliveryFee();
                    if ($deliveryFee > $this->deliveryFee) {
                        $this->setDeliveryFee($deliveryFee);
                    }
                }
            }

            if ($item->getIsBogoFreeItem()) {
                // Handle BOGO free items
                $originalPrice = $item->calculateItemSubtotal(); // Includes modifiers
                
                $status[] = [
                    'itemId' => $item->getId(),
                    'dish' => $item->getDish()?->getName(),
                    'bundle' => $item->getBundle()?->getName(),
                    'type' => 'bogo_free',
                    'originalPrice' => $originalPrice,
                    'finalPrice' => 0,
                    'discountApplied' => $originalPrice,
                    'status' => 'free_item',
                    'deliverFee' => $deliveryFee,
                    'quantity' => $item->getQuantity(),
                    'modifierTotal' => $item->getModifierTotal() // Add modifier info
                ];
                continue;
            }

            // Handle bundle items
            if ($item->getBundle()) {
                $bundlePrice = (float) $item->getBundle()->getPrice() * $item->getQuantity();
                $status[] = [
                    'itemId' => $item->getId(),
                    'dish' => null,
                    'bundle' => $item->getBundle()->getName(),
                    'bundleId' => $item->getBundle()->getId(),
                    'type' => 'bundle',
                    'originalPrice' => $bundlePrice,
                    'finalPrice' => $bundlePrice,
                    'discountApplied' => 0,
                    'status' => 'no_discount',
                    'deliverFee' => $deliveryFee,
                    'quantity' => $item->getQuantity(),
                    'modifierTotal' => 0 // Bundles typically don't have modifiers
                ];
                continue;
            }

            // Handle dish items
            $discount = $item->getDish()?->getActiveOffer()?->getDiscount();

            if (!$discount) {
                // Item with no discount
                $itemSubtotal = $item->calculateItemSubtotal(); // Includes modifiers
                $status[] = [
                    'itemId' => $item->getId(),
                    'dish' => $item->getDish()?->getName(),
                    'bundle' => null,
                    'type' => 'regular',
                    'originalPrice' => $itemSubtotal,
                    'finalPrice' => $itemSubtotal,
                    'discountApplied' => 0,
                    'status' => 'no_discount',
                    'deliverFee' => $deliveryFee,
                    'quantity' => $item->getQuantity(),
                    'modifierTotal' => $item->getModifierTotal(), // Add modifier info
                    'basePrice' => (float) $item->getUnitPrice() * $item->getQuantity()
                ];
                continue;
            }

            // Item with potential discount
            $minOrderAmount = (float) ($discount->getMinOrderAmount() ?? 0);
            $discountType = $discount->getDiscountType();
            $discountValue = (float) $discount->getValue();
            $itemSubtotal = $item->calculateItemSubtotal(); // Includes modifiers

            $eligible = $minOrderAmount === 0 || $baseSubtotal >= $minOrderAmount;

            // Calculate what the discount would be if eligible
            $potentialDiscount = 0;
            if ($eligible) {
                if ($discountType === 'percentage') {
                    $potentialDiscount = $itemSubtotal * ($discountValue / 100);
                } elseif ($discountType === 'fixed_amount') {
                    $potentialDiscount = $discountValue * $item->getQuantity();
                }
            }

            $finalPrice = $itemSubtotal - $potentialDiscount;

            $status[] = [
                'itemId' => $item->getId(),
                'dish' => $item->getDish()?->getName(),
                'bundle' => null,
                'type' => $discountType,
                'originalPrice' => $itemSubtotal,
                'finalPrice' => $finalPrice,
                'discountValue' => $discountValue,
                'discountType' => $discountType,
                'minOrderAmount' => $minOrderAmount,
                'orderSubtotal' => $baseSubtotal,
                'discountEligible' => $eligible,
                'discountApplied' => $eligible ? $potentialDiscount : 0,
                'status' => $eligible ? 'discount_applied' : 'min_order_not_met',
                'quantity' => $item->getQuantity(),
                'modifierTotal' => $item->getModifierTotal(), // Add modifier info
                'basePrice' => (float) $item->getUnitPrice() * $item->getQuantity()
            ];
        }

        // Add order-level summary
        $status['summary'] = [
            'baseSubtotal' => $baseSubtotal,
            'finalSubtotal' => (float) $this->subtotal,
            'totalDiscount' => (float) $this->totalDiscount,
            'deliveryFee' => (float) $this->deliveryFee,
            'taxAmount' => (float) $this->taxAmount,
            'grandTotal' => (float) $this->grandTotal,
            'minOrderRequirementMet' => $this->checkOverallMinOrderRequirement()
        ];

        return $status;
    }

    // Helper method to check if overall min order requirements are met
    private function checkOverallMinOrderRequirement(): bool
    {
        $baseSubtotal = 0;

        foreach ($this->orderItem as $item) {
            if ($item->getIsBogoFreeItem())
                continue;

            // Use calculateItemSubtotal() which includes modifiers
            $baseSubtotal += $item->calculateItemSubtotal();
        }

        // Check if ANY discount requires min order that isn't met
        foreach ($this->orderItem as $item) {
            // Only check dishes for discounts (bundles typically don't have individual discounts)
            $discount = $item->getDish()?->getActiveOffer()?->getDiscount();
            if (!$discount)
                continue;

            $minOrderAmount = (float) ($discount->getMinOrderAmount() ?? 0);
            if ($minOrderAmount > 0 && $baseSubtotal < $minOrderAmount) {
                return false;
            }
        }

        return true;
    }

    //  to track free shipping status
    #[Groups(['order:read'])]
    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $hasFreeShipping = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?bool $previewOrder = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $orderType = null;

    #[ORM\ManyToOne(targetEntity: LoyaltyReward::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?LoyaltyReward $appliedReward = null;

    #[Groups(['order:read', 'order:write'])]
    #[ORM\OneToOne(mappedBy: 'order', cascade: ['persist', 'remove'])]
    private ?AdvancedOrder $advancedOrder = null;

    #[Groups(['order:read', 'order:write'])]
    #[ORM\Column(nullable: true)]
    private ?bool $isAdvancedOrder = null;


    public function getAppliedReward(): ?LoyaltyReward
    {
        return $this->appliedReward;
    }
    public function setAppliedReward(?LoyaltyReward $appliedReward): static
    {
        $this->appliedReward = $appliedReward;
        return $this;
    }

    public function getHasFreeShipping(): ?bool
    {
        return $this->hasFreeShipping;
    }

    public function setHasFreeShipping(?bool $hasFreeShipping): static
    {
        $this->hasFreeShipping = $hasFreeShipping;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeliveryAddress(): string
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(string $deliveryAddress): static
    {
        $this->deliveryAddress = $deliveryAddress;

        return $this;
    }

    public function getOrderStatus(): ?string
    {
        return $this->orderStatus;
    }

    public function setOrderStatus(?string $orderStatus): static
    {
        $this->orderStatus = $orderStatus;

        return $this;
    }

    public function getSubtotal(): ?string
    {
        return $this->subtotal;
    }

    public function setSubtotal(?string $subtotal): static
    {
        $this->subtotal = $subtotal;

        return $this;
    }

    public function getDeliveryFee(): ?string
    {
        return $this->deliveryFee;
    }

    public function setDeliveryFee(?string $deliveryFee): static
    {
        $this->deliveryFee = $deliveryFee;

        return $this;
    }

    public function getTotalDiscount(): ?string
    {
        return $this->totalDiscount;
    }

    public function setTotalDiscount(?string $totalDiscount): static
    {
        $this->totalDiscount = $totalDiscount;

        return $this;
    }

    public function getGrandTotal(): ?string
    {
        return $this->grandTotal;
    }

    public function setGrandTotal(?string $grandTotal): static
    {
        $this->grandTotal = $grandTotal;

        return $this;
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

    /**
     * @return Collection<int, OrderDiscount>
     */
    public function getOrderDiscount(): Collection
    {
        return $this->orderDiscount;
    }

    public function addOrderDiscount($orderDiscount): static
    {
        if (!$this->orderDiscount->contains($orderDiscount)) {
            $this->orderDiscount->add($orderDiscount);
            $orderDiscount->setOrder($this);
        }

        return $this;
    }

    public function removeOrderDiscount($orderDiscount): static
    {
        if ($this->orderDiscount->removeElement($orderDiscount)) {
            // set the owning side to null (unless already changed)
            if ($orderDiscount->getOrder() === $this) {
                $orderDiscount->setOrder(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    #[Groups(['order:read'])]
    public function getOrderItem(): Collection
    {
        return $this->orderItem;
    }

    public function addOrderItem(OrderItem $orderItem): static
    {
        if (!$this->orderItem->contains($orderItem)) {
            $this->orderItem->add($orderItem);
            $orderItem->setOrder($this);
        }

        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): static
    {
        if ($this->orderItem->removeElement($orderItem)) {
            // set the owning side to null (unless already changed)
            if ($orderItem->getOrder() === $this) {
                $orderItem->setOrder(null);
            }
        }

        return $this;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(?string $orderNumber): static
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    public function getOrderDate(): ?\DateTime
    {
        return $this->orderDate;
    }

    public function setOrderDate(?\DateTime $orderDate): static
    {
        $this->orderDate = $orderDate;

        return $this;
    }

    public function getOrderChannel(): ?string
    {
        return $this->orderChannel;
    }

    public function setOrderChannel(?string $orderChannel): static
    {
        $this->orderChannel = $orderChannel;

        return $this;
    }

    public function getCreatedOn(): ?\DateTime
    {
        return $this->createdOn;
    }

    public function setCreatedOn(?\DateTime $createdOn): static
    {
        $this->createdOn = $createdOn;

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

    public function getPreviewOrder(): ?bool
    {
        return $this->previewOrder;
    }

    public function setPreviewOrder(?bool $previewOrder): static
    {
        $this->previewOrder = $previewOrder;

        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getOrderType(): ?string
    {
        return $this->orderType;
    }

    public function setOrderType(?string $orderType): static
    {
        $this->orderType = $orderType;

        return $this;
    }

    public function getAdvancedOrder(): ?AdvancedOrder
    {
        return $this->advancedOrder;
    }

    public function setAdvancedOrder(?AdvancedOrder $advancedOrder): static
    {
        // unset the owning side of the relation if necessary
        if ($advancedOrder === null && $this->advancedOrder !== null) {
            $this->advancedOrder->setOrder(null);
        }

        // set the owning side of the relation if necessary
        if ($advancedOrder !== null && $advancedOrder->getOrder() !== $this) {
            $advancedOrder->setOrder($this);
        }

        $this->advancedOrder = $advancedOrder;

        return $this;
    }

    public function getIsAdvancedOrder(): ?bool
    {
        return $this->isAdvancedOrder;
    }

    public function setIsAdvancedOrder(?bool $isAdvancedOrder): static
    {
        $this->isAdvancedOrder = $isAdvancedOrder;

        return $this;
    }

}