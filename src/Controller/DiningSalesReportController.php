<?php
// src/Controller/SalesReportController.php

namespace App\Controller;

use App\Service\DiningSalesReportGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DiningSalesReportController extends AbstractController
{
    #[Route('/api/dining/sales/reports/daily/{date}', name: 'api_dining_sales_daily', methods: ['GET'])]
    public function dailyReport(string $date, DiningSalesReportGenerator $reportGenerator): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            return new JsonResponse(['error' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
        }
        
        $report = $reportGenerator->generateDailyReport($dateObj);
        
        return new JsonResponse($report);
    }

    #[Route('/api/dining/sales/reports/weekly/{date}', name: 'api_dining_sales_weekly', methods: ['GET'])]
    public function weeklyReport(string $date, DiningSalesReportGenerator $reportGenerator): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            return new JsonResponse(['error' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
        }
        
        $report = $reportGenerator->generateWeeklyReport($dateObj);
        
        return new JsonResponse($report);
    }

    #[Route('/api/dining/sales/reports/monthly/{date}', name: 'api_dining_sales_monthly', methods: ['GET'])]
    public function monthlyReport(string $date, DiningSalesReportGenerator $reportGenerator): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            return new JsonResponse(['error' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
        }
        
        $report = $reportGenerator->generateMonthlyReport($dateObj);
        
        return new JsonResponse($report);
    }

    #[Route('/api/dining/sales/reports/range', name: 'api_dining_sales_range', methods: ['GET'])]
    public function dateRangeReport(Request $request, DiningSalesReportGenerator $reportGenerator): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $startDate = $request->query->get('start');
        $endDate = $request->query->get('end');
        
        if (!$startDate || !$endDate) {
            return new JsonResponse(['error' => 'Start and end dates are required.'], 400);
        }
        
        $start = \DateTime::createFromFormat('Y-m-d', $startDate);
        $end = \DateTime::createFromFormat('Y-m-d', $endDate);
        
        if (!$start || !$end) {
            return new JsonResponse(['error' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
        }
        
        $report = $reportGenerator->generateCustomReport($start, $end);
        
        return new JsonResponse($report);
    }

    #[Route('/api/dining/sales/dashboard', name: 'api_dining_sales_dashboard', methods: ['GET'])]
    public function dashboard(DiningSalesReportGenerator $reportGenerator): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Today's report
        $today = new \DateTime();
        $todayReport = $reportGenerator->generateDailyReport($today);
        
        // Yesterday's report
        $yesterday = (clone $today)->modify('-1 day');
        $yesterdayReport = $reportGenerator->generateDailyReport($yesterday);
        
        // This week's report
        $thisWeekReport = $reportGenerator->generateWeeklyReport($today);
        
        // This month's report
        $thisMonthReport = $reportGenerator->generateMonthlyReport($today);

        // Get hourly data specifically for the dashboard
        $hourlyData = $reportGenerator->getTodayHourlyData();
        
        $dashboardData = [
            'today' => $todayReport['summary'],
            'yesterday' => $yesterdayReport['summary'],
            'this_week' => $thisWeekReport['summary'],
            'this_month' => $thisMonthReport['summary'],
            'top_items_today' => array_slice($todayReport['top_items'], 0, 5),
            'time_series' => $hourlyData 
        ];
        
        return new JsonResponse($dashboardData);
    }
}