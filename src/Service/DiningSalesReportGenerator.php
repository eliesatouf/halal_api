<?php
// src/Service/SalesReportGenerator.php

namespace App\Service;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;

class DiningSalesReportGenerator
{
    private $orderRepository;
    private $entityManager;

    public function __construct(
        OrderRepository $orderRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->orderRepository = $orderRepository;
        $this->entityManager = $entityManager;
    }

    public function getTodayHourlyData(): array
    {
        $today = new \DateTime();
        $start = clone $today;
        $start->setTime(0, 0, 0);
        $end = clone $today;
        $end->setTime(23, 59, 59);
        
        $orders = $this->orderRepository->findCompletedOrdersByDateRange($start, $end);
        return $this->calculateTimeSeries($orders, $start, $end, 'daily');
    }


    public function generateDailyReport(\DateTimeInterface $date): array
    {
        $start = clone $date;
        $start->setTime(0, 0, 0);
        $end = clone $date;
        $end->setTime(23, 59, 59);
        
        return $this->generateReport($start, $end, 'daily');
    }

    public function generateWeeklyReport(\DateTimeInterface $date): array
    {
        $start = clone $date;
        $start->modify('monday this week')->setTime(0, 0, 0);
        $end = clone $date;
        $end->modify('sunday this week')->setTime(23, 59, 59);
        
        return $this->generateReport($start, $end, 'weekly');
    }

    public function generateMonthlyReport(\DateTimeInterface $date): array
    {
        $start = clone $date;
        $start->modify('first day of this month')->setTime(0, 0, 0);
        $end = clone $date;
        $end->modify('last day of this month')->setTime(23, 59, 59);
        
        return $this->generateReport($start, $end, 'monthly');
    }

    public function generateCustomReport(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $end->setTime(23, 59, 59);
        return $this->generateReport($start, $end, 'custom');
    }

    private function generateReport(\DateTimeInterface $start, \DateTimeInterface $end, string $type): array
    {
        // Get completed orders only for accurate sales reporting
        $orders = $this->orderRepository->findCompletedOrdersByDateRange($start, $end);
        
        $reportData = array(
            'period' => array(
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s'),
                'type' => $type
            ),
            'summary' => $this->calculateSummary($orders),
            'details' => $this->calculateDetails($orders),
            'time_series' => $this->calculateTimeSeries($orders, $start, $end, $type),
            'top_items' => $this->calculateTopItems($orders),
            'discount_analysis' => $this->analyzeDiscounts($orders),
            'channel_analysis' => $this->analyzeChannels($orders)
        );
        
        return $reportData;
    }

    private function calculateSummary(array $orders): array
    {
        $totalOrders = count($orders);
        $totalRevenue = 0;
        $totalDiscounts = 0;
        $totalDeliveryFees = 0;
        $avgOrderValue = 0;
        
        foreach ($orders as $order) {
            $totalRevenue += (float) $order->getGrandTotal();
            $totalDiscounts += (float) $order->getTotalDiscount();
            $totalDeliveryFees += (float) ($order->getDeliveryFee() ?? 0);
        }
        
        if ($totalOrders > 0) {
            $avgOrderValue = $totalRevenue / $totalOrders;
        }
        
        return array(
            'total_orders' => $totalOrders,
            'total_revenue' => round($totalRevenue, 2),
            'total_discounts' => round($totalDiscounts, 2),
            'total_delivery_fees' => round($totalDeliveryFees, 2),
            'average_order_value' => round($avgOrderValue, 2),
            'net_revenue' => round($totalRevenue - $totalDiscounts, 2)
        );
    }

    private function calculateDetails(array $orders): array
    {
        $details = array();
        
        foreach ($orders as $order) {
            $orderData = array(
                'order_number' => $order->getOrderNumber(),
                'order_date' => $order->getOrderDate()->format('Y-m-d H:i:s'),
                'customer' => $order->getCustomer() ? $order->getCustomer()->getId() : null,
                'subtotal' => (float) $order->getSubtotal(),
                'discount' => (float) $order->getTotalDiscount(),
                'delivery_fee' => (float) $order->getDeliveryFee(),
                'grand_total' => (float) $order->getGrandTotal(),
                'channel' => $order->getOrderChannel(),
                'status' => $order->getOrderStatus(),
                'items' => array()
            );
            
            foreach ($order->getOrderItem() as $item) {
                if (!$item->getIsBogoFreeItem()) {
                    $orderData['items'][] = array(
                        'dish' => $item->getDish() ? $item->getDish()->getName() : 'Unknown',
                        'quantity' => $item->getQuantity(),
                        'unit_price' => (float) $item->getUnitPrice(),
                        'total_price' => (float) $item->getTotalPrice()
                    );
                }
            }
            
            $details[] = $orderData;
        }
        
        return $details;
    }

    private function calculateTimeSeries(array $orders, \DateTimeInterface $start, \DateTimeInterface $end, string $type): array
{
    $timeSeries = array();
    
    try {
        // Initialize time buckets based on report type
        if ($type === 'daily') {
            // Initialize all 24 hours with zero values
            for ($hour = 0; $hour < 24; $hour++) {
                $hourKey = sprintf('%02d:00', $hour);
                $timeSeries[$hourKey] = array(
                    'orders' => 0,
                    'revenue' => 0.0,
                    'customers' => array(),
                    'unique_customers' => 0
                );
            }
            
            foreach ($orders as $order) {
                if (!$order->getOrderDate()) {
                    continue;
                }
                
                $hour = (int)$order->getOrderDate()->format('H');
                $hourKey = sprintf('%02d:00', $hour);
                
                // Safely access the time slot
                if (!isset($timeSeries[$hourKey])) {
                    $timeSeries[$hourKey] = array(
                        'orders' => 0,
                        'revenue' => 0.0,
                        'customers' => array(),
                        'unique_customers' => 0
                    );
                }
                
                $timeSeries[$hourKey]['orders']++;
                $timeSeries[$hourKey]['revenue'] += (float) ($order->getGrandTotal() ?? 0);
                
                $customerId = $order->getCustomer() ? $order->getCustomer()->getId() : 'unknown';
                if ($customerId && !in_array($customerId, $timeSeries[$hourKey]['customers'])) {
                    $timeSeries[$hourKey]['customers'][] = $customerId;
                    $timeSeries[$hourKey]['unique_customers'] = count($timeSeries[$hourKey]['customers']);
                }
            }
        } else {
            // For weekly/monthly reports, group by day
            $interval = \DateInterval::createFromDateString('1 day');
            $period = new \DatePeriod($start, $interval, $end);
            
            // Initialize all days in the period
            foreach ($period as $day) {
                $dayStr = $day->format('Y-m-d');
                $timeSeries[$dayStr] = array(
                    'orders' => 0,
                    'revenue' => 0.0,
                    'customers' => array(),
                    'unique_customers' => 0
                );
            }
            
            foreach ($orders as $order) {
                if (!$order->getOrderDate()) {
                    continue;
                }
                
                $dayStr = $order->getOrderDate()->format('Y-m-d');
                
                // Make sure the day exists in our time series
                if (!isset($timeSeries[$dayStr])) {
                    $timeSeries[$dayStr] = array(
                        'orders' => 0,
                        'revenue' => 0.0,
                        'customers' => array(),
                        'unique_customers' => 0
                    );
                }
                
                $timeSeries[$dayStr]['orders']++;
                $timeSeries[$dayStr]['revenue'] += (float) ($order->getGrandTotal() ?? 0);
                
                $customerId = $order->getCustomer() ? $order->getCustomer()->getId() : 'unknown';
                if ($customerId && !in_array($customerId, $timeSeries[$dayStr]['customers'])) {
                    $timeSeries[$dayStr]['customers'][] = $customerId;
                    $timeSeries[$dayStr]['unique_customers'] = count($timeSeries[$dayStr]['customers']);
                }
            }
        }
    } catch (\Exception $e) {
        // Log error and return empty time series
        error_log('Error calculating time series: ' . $e->getMessage());
        return array();
    }
    
    return $timeSeries;
}

    private function calculateTopItems(array $orders): array
    {
        $items = array();
        
        foreach ($orders as $order) {
            foreach ($order->getOrderItem() as $item) {
                if ($item->getIsBogoFreeItem()) continue;
                
                $dishName = $item->getDish() ? $item->getDish()->getName() : 'Unknown';
                
                if (!isset($items[$dishName])) {
                    $items[$dishName] = array(
                        'name' => $dishName,
                        'quantity' => 0,
                        'revenue' => 0,
                        'orders' => 0
                    );
                }
                
                $items[$dishName]['quantity'] += $item->getQuantity();
                $items[$dishName]['revenue'] += (float) $item->getTotalPrice();
                $items[$dishName]['orders'] += 1;
            }
        }
        
        // Sort by revenue descending
        usort($items, function($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });
        
        return array_slice($items, 0, 10); // Return top 10 items
    }

    private function analyzeDiscounts(array $orders): array
    {
        $discountAnalysis = array(
            'total_discounts' => 0,
            'discounted_orders' => 0,
            'average_discount_per_order' => 0,
            'discount_types' => array()
        );
        
        foreach ($orders as $order) {
            $discount = (float) $order->getTotalDiscount();
            
            if ($discount > 0) {
                $discountAnalysis['total_discounts'] += $discount;
                $discountAnalysis['discounted_orders']++;
                
                // Analyze discount types from discountStatus if available
                $discountStatus = $order->getDiscountStatus();
                if (is_array($discountStatus)) {
                    foreach ($discountStatus as $key => $status) {
                        if ($key === 'summary') continue;
                        
                        $discountType = isset($status['type']) ? $status['type'] : 'unknown';
                        if (!isset($discountAnalysis['discount_types'][$discountType])) {
                            $discountAnalysis['discount_types'][$discountType] = array(
                                'count' => 0,
                                'amount' => 0
                            );
                        }
                        
                        $discountAmount = isset($status['discountApplied']) ? $status['discountApplied'] : 0;
                        $discountAnalysis['discount_types'][$discountType]['count']++;
                        $discountAnalysis['discount_types'][$discountType]['amount'] += $discountAmount;
                    }
                }
            }
        }
        
        if ($discountAnalysis['discounted_orders'] > 0) {
            $discountAnalysis['average_discount_per_order'] = 
                $discountAnalysis['total_discounts'] / $discountAnalysis['discounted_orders'];
        }
        
        return $discountAnalysis;
    }

    private function analyzeChannels(array $orders): array
    {
        $channels = array();
        
        foreach ($orders as $order) {
            $channel = $order->getOrderChannel() ?? 'unknown';
            
            if (!isset($channels[$channel])) {
                $channels[$channel] = array(
                    'orders' => 0,
                    'revenue' => 0,
                    'average_order_value' => 0
                );
            }
            
            $channels[$channel]['orders']++;
            $channels[$channel]['revenue'] += (float) $order->getGrandTotal();
        }
        
        // Calculate average order value per channel
        foreach ($channels as $channel => $data) {
            if ($data['orders'] > 0) {
                $channels[$channel]['average_order_value'] = $data['revenue'] / $data['orders'];
            }
        }
        
        return $channels;
    }
}