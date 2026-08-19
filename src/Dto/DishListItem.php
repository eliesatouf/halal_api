<?php
namespace App\Dto;

class DishListItem
{
    public int $id;
    public string $type;
    public string $menu;
    public string $name;
    public ?string $description;
    public int $price;
    public ?int $discountedPrice;
    public ?string $imageSource;
    public string $status;
    public bool $active;
    public bool $enableAddToCart;
    public ?int $prepTime;
    public ?array $loyverseModifiersIds;
    
    // Category data
    public int $categoryId;
    public string $categoryName;
    public string $categorySlug;
    
    // Variants as simple array
    public array $variants = [];
    
    // Computed fields
    public array $promoStatus = [
        'active' => false,
        'discount' => 0,
        'description' => '',
        'isBogo' => false
    ];
}