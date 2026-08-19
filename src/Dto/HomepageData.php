<?php
// src/Dto/HomepageData.php

namespace App\Dto;

use App\Entity\Landingpage;
use App\Entity\FeaturedItem;
use App\Entity\Category;
use App\Entity\Dish;
use App\Entity\Offer;
use App\Entity\Modifier;
use App\Entity\ModifierOption;
use App\Entity\Variant;
use App\Entity\Settings;
use Symfony\Component\Serializer\Annotation\Groups;

class HomepageData
{
    #[Groups(['homepage:read'])]
    public ?Landingpage $landingPage = null;  // Changed from ?array to ?Landingpage

    #[Groups(['homepage:read'])]
    public ?string $workingHours = null;

    #[Groups(['homepage:read'])]
    public ?array $featuredItems = null;  // Array of FeaturedItem objects

    #[Groups(['homepage:read'])]
    public ?array $categories = null;     // Array of Category objects

    #[Groups(['homepage:read'])]
    public ?array $dishes = null;          // Array of Dish objects

    #[Groups(['homepage:read'])]
    public ?array $offers = null;           // Array of Offer objects

    #[Groups(['homepage:read'])]
    public ?array $modifiers = null;        // Array of Modifier objects

    #[Groups(['homepage:read'])]
    public ?array $variants = null;

    #[Groups(['homepage:read'])]
    public ?array $modifierOptions = null;  // Array of ModifierOption objects

    #[Groups(['homepage:read'])]
    public ?string $closingDate = null;

    #[Groups(['homepage:read'])]
    public ?string $closingMessage = null;

    #[Groups(['homepage:read'])]
    public ?string $reopeningMessage = null;

    #[Groups(['homepage:read'])]
    public ?string $firstOrderPromoEnabled = null;

    #[Groups(['homepage:read'])]
    public ?string $firstOrderPercentage = null;
}