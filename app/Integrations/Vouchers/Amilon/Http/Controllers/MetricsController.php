<?php

namespace App\Integrations\Vouchers\Amilon\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Integrations\Vouchers\Amilon\Services\AmilonMetricsService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

#[Group('Modules/Vouchers/Amilon/Metrics')]
class MetricsController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AmilonMetricsService $metricsService
    ) {}

    /**
     * Get all metrics for Amilon vouchers.
     */
    #[RequiresPermission(PermissionDefaults::CREATE_VOUCHER)]
    public function index(Request $request): JsonResponse
    {
        // Parse date range from request
        $dateRange = $this->parseDateRange($request);
        $from = $dateRange['from'];
        $to = $dateRange['to'];

        // Calculate all metrics
        $metrics = $this->metricsService->calculateAllMetrics($from, $to);

        return response()->json([
            'data' => $metrics,
        ]);
    }

    /**
     * Get total purchase volume for Amilon vouchers.
     */
    #[RequiresPermission(PermissionDefaults::CREATE_VOUCHER)]
    public function totalPurchaseVolume(Request $request): JsonResponse
    {
        // Parse date range from request
        $dateRange = $this->parseDateRange($request);
        $from = $dateRange['from'];
        $to = $dateRange['to'];

        // Calculate total purchase volume
        $totalPurchaseVolume = $this->metricsService->calculateTotalPurchaseVolume($from, $to);

        return response()->json([
            'data' => [
                'period' => [
                    'from' => $from ? $from->toDateString() : Carbon::now()->subDays(30)->toDateString(),
                    'to' => $to ? $to->toDateString() : Carbon::now()->toDateString(),
                ],
                'total_purchase_volume' => $totalPurchaseVolume,
            ],
        ]);
    }

    /**
     * Get adoption rate for Amilon vouchers.
     */
    #[RequiresPermission(PermissionDefaults::CREATE_VOUCHER)]
    public function adoptionRate(Request $request): JsonResponse
    {
        // Parse date range from request
        $dateRange = $this->parseDateRange($request);
        $from = $dateRange['from'];
        $to = $dateRange['to'];

        // Calculate adoption rate
        $adoptionRate = $this->metricsService->calculateAdoptionRate($from, $to);

        return response()->json([
            'data' => [
                'period' => [
                    'from' => $from ? $from->toDateString() : Carbon::now()->subDays(30)->toDateString(),
                    'to' => $to ? $to->toDateString() : Carbon::now()->toDateString(),
                ],
                'adoption_rate' => $adoptionRate,
            ],
        ]);
    }

    /**
     * Get average amount per employee for Amilon vouchers.
     */
    #[RequiresPermission(PermissionDefaults::CREATE_VOUCHER)]
    public function averageAmountPerEmployee(Request $request): JsonResponse
    {
        // Parse date range from request
        $dateRange = $this->parseDateRange($request);
        $from = $dateRange['from'];
        $to = $dateRange['to'];

        // Calculate average amount per employee
        $averageAmount = $this->metricsService->calculateAverageAmountPerEmployee($from, $to);

        return response()->json([
            'data' => [
                'period' => [
                    'from' => $from ? $from->toDateString() : Carbon::now()->subDays(30)->toDateString(),
                    'to' => $to ? $to->toDateString() : Carbon::now()->toDateString(),
                ],
                'average_amount_per_employee' => $averageAmount,
            ],
        ]);
    }

    /**
     * Get number of vouchers purchased for Amilon vouchers.
     */
    #[RequiresPermission(PermissionDefaults::CREATE_VOUCHER)]
    public function vouchersPurchased(Request $request): JsonResponse
    {
        // Parse date range from request
        $dateRange = $this->parseDateRange($request);
        $from = $dateRange['from'];
        $to = $dateRange['to'];

        // Count vouchers purchased
        $vouchersPurchased = $this->metricsService->countVouchersPurchased($from, $to);

        return response()->json([
            'data' => [
                'period' => [
                    'from' => $from ? $from->toDateString() : Carbon::now()->subDays(30)->toDateString(),
                    'to' => $to ? $to->toDateString() : Carbon::now()->toDateString(),
                ],
                'vouchers_purchased' => $vouchersPurchased,
            ],
        ]);
    }

    /**
     * Get top merchants for Amilon vouchers.
     */
    #[RequiresPermission(PermissionDefaults::CREATE_VOUCHER)]
    public function topMerchants(Request $request): JsonResponse
    {
        // Parse date range from request
        $dateRange = $this->parseDateRange($request);
        $from = $dateRange['from'];
        $to = $dateRange['to'];
        $limitParam = $request->input('limit', 10);
        $limit = is_numeric($limitParam) ? (int) $limitParam : 10;

        // Get top merchants
        $topMerchants = $this->metricsService->getTopMerchants($from, $to, $limit);

        return response()->json([
            'data' => [
                'period' => [
                    'from' => $from ? $from->toDateString() : Carbon::now()->subDays(30)->toDateString(),
                    'to' => $to ? $to->toDateString() : Carbon::now()->toDateString(),
                ],
                'top_merchants' => $topMerchants,
            ],
        ]);
    }

    /**
     * Helper to safely parse date from request input.
     *
     * @return array{from: Carbon|null, to: Carbon|null}
     */
    private function parseDateRange(Request $request): array
    {
        $fromInput = $request->input('from');
        $toInput = $request->input('to');

        $from = $fromInput && is_string($fromInput) ? Carbon::parse($fromInput)->startOfDay() : null;
        $to = $toInput && is_string($toInput) ? Carbon::parse($toInput)->endOfDay() : null;

        return ['from' => $from, 'to' => $to];
    }
}
