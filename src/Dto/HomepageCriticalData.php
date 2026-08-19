<?php
// src/Dto/HomepageCriticalData.php

namespace App\Dto;

use App\Entity\Landingpage;
use Symfony\Component\Serializer\Annotation\Groups;

class HomepageCriticalData
{
    #[Groups(['homepage:read'])]
    public ?Landingpage $landingPage = null;

    #[Groups(['homepage:read'])]
    public ?string $workingHours = null;

    #[Groups(['homepage:read'])]
    public ?string $closingDate = null;

    #[Groups(['homepage:read'])]
    public ?string $closingMessage = null;

    #[Groups(['homepage:read'])]
    public ?string $reopeningMessage = null;

    #[Groups(['homepage:read'])]
    public ?string $firstOrderPercentage = null;

    #[Groups(['homepage:read'])]
    public ?string $firstOrderPromoEnabled = null;
}