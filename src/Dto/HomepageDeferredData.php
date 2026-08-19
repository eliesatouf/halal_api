<?php
// src/Dto/HomepageDeferredData.php

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class HomepageDeferredData
{
    #[Groups(['homepage:read'])]
    public array $featuredItems = [];

    #[Groups(['homepage:read'])]
    public array $categories = [];

    #[Groups(['homepage:read'])]
    public array $dishes = [];

    #[Groups(['homepage:read'])]
    public array $offers = [];

    #[Groups(['homepage:read'])]
    public array $modifiers = [];

    #[Groups(['homepage:read'])]
    public array $modifierOptions = [];

    #[Groups(['homepage:read'])]
    public array $variants = [];
}