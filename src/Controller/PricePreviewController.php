<?php
// App\Controller\PricePreviewController

namespace App\Controller;

use App\Entity\Dish;
use App\Entity\LoyaltyReward;
use App\Service\PriceCalculatorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/api/price-preview')]
class PricePreviewController extends AbstractController
{
    public function __construct(
        private PriceCalculatorService $priceCalculator
    ) {}

    #[Route('', name: 'price_preview', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function previewPrice(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $dishId = $data['dishId'] ?? null;
        $rewardId = $data['rewardId'] ?? null;
        
        $dish = $entityManager->getRepository(Dish::class)->find($dishId);
        $reward = $rewardId ? $entityManager->getRepository(LoyaltyReward::class)->find($rewardId) : null;
        
        if (!$dish) {
            return $this->json(['error' => 'Service not found'], 404);
        }
        
        $customer = $this->getUser()->getCustomer();
        $calculation = $this->priceCalculator->calculateFinalPrice(
            $dish->getPrice(),
            $dish,
            $customer,
            $reward
        );
        
        return $this->json([
            'basePrice' => $calculation->basePrice,
            'finalPrice' => $calculation->finalPrice,
            'appliedDiscount' => $calculation->appliedDiscount,
            'rewardDetails' => $calculation->rewardDetails,
            'isEligible' => $calculation->isEligibleForReward,
            'applicableRewards' => $this->priceCalculator->getApplicableRewards($customer, $dish)
        ]);
    }
}