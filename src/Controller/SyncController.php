<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\LoyverseSyncService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
 

final class SyncController extends AbstractController
{
    #[Route('/api/syncCategories', name: 'sync_categories',  methods: ['GET'])]
    public function syncCategories(Request $request, LoyverseSyncService $syncService): Response
    {
        
        $categoryCount = $syncService->syncCategoriesFromLoyverse();
        return new JsonResponse([
                'message' => $categoryCount]);

    }

    #[Route('/api/syncItems', name: 'sync_items',  methods: ['GET'])]
    public function syncItems(Request $request, LoyverseSyncService $syncService): Response
    {
        
        $count = $syncService->syncItemsFromLoyverse();
        return new JsonResponse([
                'message' => $count]);

    }

    #[Route('/api/syncCustomer', name: 'sync_customer',  methods: ['GET'])]
    public function syncCustomer(Request $request, LoyverseSyncService $syncService): Response
    {
        
        $count = $syncService->syncCustomersFromLoyverse();
        return new JsonResponse([
                'message' => $count]);

    }

    #[Route('/api/syncPaymentTypes', name: 'sync_payment_types',  methods: ['GET'])]
    public function syncPaymentTypes(Request $request, LoyverseSyncService $syncService): Response
    {
        
        $count = $syncService->syncPaymentTypesFromLoyverse();
        return new JsonResponse([
                'message' => $count]);

    }

    #[Route('/api/syncModifiers', name: 'sync_modifiers',  methods: ['GET'])]
    public function syncModifiers(Request $request, LoyverseSyncService $syncService): Response
    {
        
        $count = $syncService->syncModifiersFromLoyverse();
        return new JsonResponse([
                'message' => $count]);
    }
}
