<?php

namespace App\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Integrations\Vouchers\Amilon\Enums\OrderStatus;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Models\FinancerUser;
use Carbon\Carbon;

class VoucherAverageAmountCalculator extends BaseMetricCalculator
{
    /**
     * Calculate voucher average amount metric
     * Returns average amount in euros by day for confirmed orders
     *
     * @return array{total: float, daily: array<int, array{date: string, count: float}>}
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
            ->select('amount', 'created_at')
            ->get()
            ->groupBy(function (Order $order): string {
                return $order->created_at !== null ? $order->created_at->toDateString() : now()->toDateString();
            });

        // Process day by day in memory (no more DB queries in loop)
        $dailyStats = [];
        $currentDate = $dateFrom->copy()->startOfDay();
        $allAmounts = [];

        while ($currentDate <= $dateTo) {
            $dateKey = $currentDate->toDateString();

            // Get orders for this day from pre-loaded data
            $dayOrders = $allOrders->get($dateKey) ?? collect();

            $dayAverage = 0;
            if ($dayOrders->isNotEmpty()) {
                $avgResult = $dayOrders->avg('amount');
                $dayAverage = $avgResult !== null ? round($avgResult, 2) : 0.0;
                /** @var Order $order */
                foreach ($dayOrders as $order) {
                    $allAmounts[] = is_numeric($order->amount) ? (float) $order->amount : 0.0;
                }
            }

            $dailyStats[] = [
                'date' => $dateKey,
                'count' => $dayAverage,
            ];

            $currentDate->addDay();
        }

        // Calculate overall average
        $overallAverage = 0;
        if ($allAmounts !== []) {
            $overallAverage = round(array_sum($allAmounts) / count($allAmounts), 2);
        }

        return [
            'total' => $overallAverage,
            'daily' => $dailyStats,
        ];
    }

    /**
     * Get the metric type
     */
    public function getMetricType(): string
    {
        return FinancerMetricType::VOUCHER_AVERAGE_AMOUNT;
    }
}
