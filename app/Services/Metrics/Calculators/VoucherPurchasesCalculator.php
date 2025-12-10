<?php

namespace App\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Integrations\Vouchers\Amilon\Enums\OrderStatus;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Models\FinancerUser;
use Carbon\Carbon;

class VoucherPurchasesCalculator extends BaseMetricCalculator
{
    /**
     * Calculate voucher purchases metric
     * Returns COUNT of purchases (number of orders) by day for confirmed orders
     *
     * @return array{total: int, daily: array<int, array{date: string, count: int}>}
     */
    public function calculate(
        string $financerId,
        Carbon $dateFrom,
        Carbon $dateTo,
        string $period
    ): array {
        // Get users linked to this financer
        $financerUserIds = FinancerUser::where('financer_id', $financerId)
            ->where('active', true)
            ->pluck('user_id');

        if ($financerUserIds->isEmpty()) {
            return [
                'total' => 0,
                'daily' => [],
            ];
        }

        // Load ALL confirmed orders in a single query (fixes N+1 query issue)
        $allOrders = Order::whereIn('user_id', $financerUserIds)
            ->where('status', OrderStatus::CONFIRMED)
            ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->select('id', 'created_at')
            ->get()
            ->groupBy(function (Order $order): string {
                return $order->created_at !== null ? $order->created_at->toDateString() : now()->toDateString();
            });

        // Process day by day in memory (no more DB queries in loop)
        $dailyStats = [];
        $currentDate = $dateFrom->copy()->startOfDay();
        $totalPurchases = 0;

        while ($currentDate <= $dateTo) {
            $dateKey = $currentDate->toDateString();

            // Get orders for this day from pre-loaded data
            $dayOrders = $allOrders->get($dateKey) ?? collect();
            $dayCount = $dayOrders->count();

            $dailyStats[] = [
                'date' => $dateKey,
                'count' => $dayCount,
            ];

            $totalPurchases += $dayCount;
            $currentDate->addDay();
        }

        return [
            'total' => $totalPurchases,
            'daily' => $dailyStats,
        ];
    }

    /**
     * Get the metric type
     */
    public function getMetricType(): string
    {
        return FinancerMetricType::VOUCHER_PURCHASES;
    }
}
