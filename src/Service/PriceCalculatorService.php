<?php
namespace App\Service;

use App\Entity\Dish;
use App\Entity\Customer;
use App\Entity\LoyaltyReward;
use App\Dto\CalculationResult;

class PriceCalculatorService
{
    public function calculateFinalPrice(
        float $basePrice,
        Dish $dish,
        Customer $customer,
        ?LoyaltyReward $appliedReward = null
    ): CalculationResult {
        $finalPrice = $basePrice;
        $appliedDiscount = 0.0;
        $rewardDetails = null;
        $isRewardEligible = false;

        if ($appliedReward && $this->isRewardApplicable($appliedReward, $dish)) {
            $isRewardEligible = true;
            $calculation = $this->applyRewardDiscount($finalPrice, $appliedReward, $dish);
            
            $finalPrice = $calculation['finalPrice'];
            $appliedDiscount = $calculation['discount'];
            $rewardDetails = $calculation['details'];
        }

        return new CalculationResult(
            basePrice: $basePrice,
            finalPrice: max(0, $finalPrice),
            appliedDiscount: $appliedDiscount,
            rewardDetails: $rewardDetails,
            isEligibleForReward: $appliedReward ? $isRewardEligible : null
        );
    }

    public function isRewardApplicable(LoyaltyReward $reward, dish $dish): bool
    {
        // Use the string directly since constants don't exist
        if ($reward->getStatus() !== 'active') {
            return false;
        }

        if ($reward->getExpiresAt() < new \DateTime()) {
            return false;
        }

        return $reward->getMilestone()->isDishEligible($dish);
    }

    public function getApplicableRewards(Customer $customer, Dish $dish): array
    {
        return array_filter(
            $customer->getLoyaltyRewards()->toArray(),
            fn(LoyaltyReward $reward) => $this->isRewardApplicable($reward, $dish)
        );
    }

    private function applyRewardDiscount(float $price, LoyaltyReward $reward, Dish $dish): array
    {
        $milestone = $reward->getMilestone();
        $originalPrice = $price;

        return match ($milestone->getRewardType()) {
            'percentage_discount' => $this->applyPercentageDiscount($price, $milestone->getRewardValue()),
            'free_dish' => $this->applyFreeDish($originalPrice, $dish),
            default => $this->noDiscountApplied($price)
        };
    }

    private function applyPercentageDiscount(float $price, float $discountPercentage): array
    {
        $discount = $price * ($discountPercentage / 100);
        $finalPrice = $price - $discount;

        return [
            'finalPrice' => $finalPrice,
            'discount' => $discount,
            'details' => "{$discountPercentage}% discount applied"
        ];
    }

    private function applyFreeDish(float $originalPrice, Dish $dish): array
    {
        return [
            'finalPrice' => 0.0,
            'discount' => $originalPrice,
            'details' => "Free {$dish->getName()} - Reward applied"
        ];
    }

    private function noDiscountApplied(float $price): array
    {
        return [
            'finalPrice' => $price,
            'discount' => 0.0,
            'details' => null
        ];
    }
}