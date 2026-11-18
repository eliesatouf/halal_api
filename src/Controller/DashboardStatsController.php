<?php
// src/Controller/DashboardStatsController.php

namespace App\Controller;

use App\Service\DashboardStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardStatsController extends AbstractController
{
    #[Route('/api/stats/dashboard', name: 'api_dashboard_stats', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function index(DashboardStatsService $stats): JsonResponse
    {
        return $this->json($stats->getStats());
    }
}