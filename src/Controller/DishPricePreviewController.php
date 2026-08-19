<?php
// src/Controller/DishPricePreviewController.php
namespace App\Controller;

use App\Entity\Dish;
use App\Entity\LoyaltyReward;
use App\Service\PriceCalculatorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api')]
class DishPricePreviewController extends AbstractController
{
    public function __construct(
        private PriceCalculatorService $priceCalculator,
        private SerializerInterface $serializer
    ) {}

    #[Route('/dish-price-preview', name: 'dish_price_preview', methods: ['POST'])]
    public function preview(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $dishId = $data['dishId'] ?? null;
        $rewardId = $data['rewardId'] ?? null;
        
        if (!$dishId) {
            return $this->json(['error' => 'Dish ID required'], 400);
        }
        
        $dish = $this->getDoctrine()->getRepository(Dish::class)->find($dishId);
        if (!$dish) {
            return $this->json(['error' => 'Dish not found'], 404);
        }
        
        $reward = null;
        if ($rewardId) {
            $reward = $this->getDoctrine()->getRepository(LoyaltyReward::class)->find($rewardId);
        }
        
        // Calculate price with reward
        $finalPrice = $dish->getPrice();
        $discount = 0;
        $rewardDetails = null;
        
        if ($reward && $reward->getStatus() === 'active') {
            $milestone = $reward->getMilestone();
            
            if ($milestone->getRewardType() === 'free_service') {
                $finalPrice = 0;
                $discount = $dish->getPrice();
                $rewardDetails = 'Free item';
            } elseif ($milestone->getRewardType() === 'percentage_discount') {
                $discountPercent = $milestone->getRewardValue();
                $discount = $dish->getPrice() * ($discountPercent / 100);
                $finalPrice = $dish->getPrice() - $discount;
                $rewardDetails = "{$discountPercent}% discount applied";
            }
        }
        
        return $this->json([
            'basePrice' => (float) $dish->getPrice(),
            'finalPrice' => round($finalPrice, 2),
            'appliedDiscount' => round($discount, 2),
            'rewardDetails' => $rewardDetails,
            'applicableRewards' => [] // You can add this later if needed
        ]);
    }
}