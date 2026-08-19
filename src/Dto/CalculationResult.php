<?php
// App\DTO\CalculationResult

namespace App\Dto;

class CalculationResult
{
    public function __construct(
        public float $basePrice,
        public float $finalPrice,
        public float $appliedDiscount,
        public ?string $rewardDetails,
        public ?bool $isEligibleForReward
    ) {}
}