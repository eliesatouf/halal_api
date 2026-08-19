<?php
namespace App\Controller;

use App\Service\LoyverseSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class DebugLoyverseController extends AbstractController
{
    #[Route('/api/debug/loyverse-status', name: 'debug_loyverse_status', methods: ['GET'])]
    public function loyverseStatus(LoyverseSyncService $loyverseSyncService): JsonResponse
    {
        $status = $loyverseSyncService->checkConnection();
        
        return $this->json([
            'loyverse_status' => $status,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
}