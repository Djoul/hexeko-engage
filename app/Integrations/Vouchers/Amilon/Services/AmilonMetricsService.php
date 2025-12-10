<?php

namespace App\Integrations\Vouchers\Amilon\Services;

use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AmilonMetricsService
{
    /**
     * Calculate the total purchase volume (total amount spent on vouchers) for a given period.
     *
     * @param  Carbon|null  $from  Start date (default: 30 days ago)
     * @param  Carbon|null  $to  End date (default: now)
     * @return float Total amount spent in euros
     */
    public function calculateTotalPurchaseVolume(?Carbon $from = null, ?Carbon $to = null): float
    {
        $from = $from ?? Carbon::now()->subDays(30)->startOfDay();
        $to = $to ?? Carbon::now()->endOfDay();

        $sum = Order::whereBetween('created_at', [$from, $to])
            ->sum('amount');

        // Convert eurocents to euros by dividing by 100
        return is_numeric($sum) ? round((float) $sum / 100, 2) : 0.0;
    }

    /**
     * Calculate the adoption rate (percentage of employees who made at least one purchase) for a given period.
     *
     * @param  Carbon|null  $from  Start date (default: 30 days ago)
     * @param  Carbon|null  $to  End date (default: now)
     * @return array<string, mixed> Adoption rate data
     */
    public function calculateAdoptionRate(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? Carbon::now()->subDays(30)->startOfDay();
        $to = $to ?? Carbon::now()->endOfDay();

        // Count total active users
        $totalUsers = User::where('enabled', true)->count();

        // Count active users who made at least one purchase
        $usersWithPurchases = Order::whereBetween('created_at', [$from, $to])
            ->whereHas('user', function ($query): void {
                $query->where('enabled', true);
            })
            ->distinct('user_id')
            ->whereNotNull('user_id')
            ->count('user_id');

        // Calculate adoption rate
        $adoptionRate = $totalUsers > 0 ? ($usersWithPurchases / $totalUsers) * 100 : 0;

        return [
            'total_users' => $totalUsers,
            'users_with_purchases' => $usersWithPurchases,
            'adoption_rate' => round($adoptionRate, 2),
        ];
    }

    /**
     * Calculate the average amount spent per active employee for a given period.
     *
     * @param  Carbon|null  $from  Start date (default: 30 days ago)
     * @param  Carbon|null  $to  End date (default: now)
     * @return array<string, mixed> Average amount data
     */
    public function calculateAverageAmountPerEmployee(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? Carbon::now()->subDays(30)->startOfDay();
        $to = $to ?? Carbon::now()->endOfDay();

        // Calculate total amount spent
        $totalAmount = $this->calculateTotalPurchaseVolume($from, $to);

        // Count active users who made purchases
        $activeUsers = Order::whereBetween('created_at', [$from, $to])
            ->whereHas('user', function ($query): void {
                $query->where('enabled', true);
            })
            ->distinct('user_id')
            ->whereNotNull('user_id')
            ->count('user_id');

        // Calculate average amount per active user
        $averageAmount = $activeUsers > 0 ? $totalAmount / $activeUsers : 0;

        return [
            'total_amount' => $totalAmount,
            'active_users' => $activeUsers,
            'average_amount' => round($averageAmount, 2),
        ];
    }

    /**
     * Count the number of vouchers purchased for a given period.
     *
     * @param  Carbon|null  $from  Start date (default: 30 days ago)
     * @param  Carbon|null  $to  End date (default: now)
     * @return int Number of transactions
     */
    public function countVouchersPurchased(?Carbon $from = null, ?Carbon $to = null): int
    {
        $from = $from ?? Carbon::now()->subDays(30)->startOfDay();
        $to = $to ?? Carbon::now()->endOfDay();

        return Order::whereBetween('created_at', [$from, $to])->count();
    }

    /**
     * Get the top merchants by transaction amount for a given period.
     *
     * @param  Carbon|null  $from  Start date (default: 30 days ago)
     * @param  Carbon|null  $to  End date (default: now)
     * @param  int  $limit  Number of top merchants to return (default: 10)
     * @return Collection Collection of top merchants with transaction amounts in euros
     */
    public function getTopMerchants(?Carbon $from = null, ?Carbon $to = null, int $limit = 10): Collection
    {
        $from = $from ?? Carbon::now()->subDays(30)->startOfDay();
        $to = $to ?? Carbon::now()->endOfDay();

        $merchants = Order::join('int_vouchers_amilon_merchants', 'int_vouchers_amilon_orders.merchant_id', '=', 'int_vouchers_amilon_merchants.merchant_id')
            ->whereBetween('int_vouchers_amilon_orders.created_at', [$from, $to])
            ->select('int_vouchers_amilon_merchants.name', 'int_vouchers_amilon_merchants.merchant_id')
            ->selectRaw('int_vouchers_amilon_merchants.merchant_id as retailer_id') // Alias for backward compatibility
            ->selectRaw('SUM(int_vouchers_amilon_orders.amount) as total_amount')
            ->selectRaw('COUNT(int_vouchers_amilon_orders.id) as transaction_count')
            ->groupBy('int_vouchers_amilon_merchants.merchant_id', 'int_vouchers_amilon_merchants.name')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get();

        // Convert eurocents to euros for each merchant's total_amount
        return $merchants->map(function ($merchant) {
            $merchant->total_amount = round((float) $merchant->total_amount / 100, 2);

            return $merchant;
        });
    }

    /**
     * Calculate all metrics for a given period.
     *
     * @param  Carbon|null  $from  Start date (default: 30 days ago)
     * @param  Carbon|null  $to  End date (default: now)
     * @return array<string, mixed> All metrics data
     */
    public function calculateAllMetrics(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? Carbon::now()->subDays(30)->startOfDay();
        $to = $to ?? Carbon::now()->startOfDay(); // https://hexeko.atlassian.net/browse/UE-332 Do not include the current day

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'total_purchase_volume' => $this->calculateTotalPurchaseVolume($from, $to),
            'adoption_rate' => $this->calculateAdoptionRate($from, $to),
            'average_amount_per_employee' => $this->calculateAverageAmountPerEmployee($from, $to),
            'vouchers_purchased' => $this->countVouchersPurchased($from, $to),
            'top_merchants' => $this->getTopMerchants($from, $to),
        ];
    }
}
