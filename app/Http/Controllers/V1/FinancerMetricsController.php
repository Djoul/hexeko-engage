<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Attributes\RequiresPermission;
use App\Enums\FinancerMetricType;
use App\Enums\IDP\PermissionDefaults;
use App\Enums\MetricPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FinancerMetricsRequest;
use App\Http\Resources\Api\V1\AllMetricsResource;
use App\Http\Resources\Api\V1\IMetricResource;
use App\Services\Metrics\MetricService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Exception;
use Illuminate\Http\JsonResponse;
use Log;

/**
 * Financer Metrics Controller
 *
 * Provides endpoints for retrieving financer-specific metrics and analytics.
 */
#[Group('Financer Metrics')]
class FinancerMetricsController extends Controller
{
    public function __construct(
        private readonly MetricService $metricService
    ) {}

    /**
     * Get all available metrics for a financer.
     *
     * Returns all available metrics including dashboard metrics and individual metrics.
     * This endpoint provides comprehensive data for complete analytics dashboards.
     *
     * @response array<string, IMetricResource>
     */
    #[RequiresPermission(PermissionDefaults::VIEW_FINANCER_METRICS)]
    #[QueryParameter('period', description: 'Période de temps pour le calcul des métriques.', type: 'string', example: '30d')]
    #[QueryParameter('refresh', description: 'Force le recalcul des métriques en ignorant le cache.', type: 'boolean', example: 'true')]
    public function getAllMetrics(FinancerMetricsRequest $request): JsonResponse
    {
        $financerId = $this->getValidFinancerId();

        if ($financerId === null) {
            return response()->json(['error' => 'No active financer ID found'], 400);
        }
        $allMetrics = [];
        $parameters = $request->validated();
        if (! array_key_exists('period', $parameters)) {
            $parameters['period'] = MetricPeriod::THIRTY_DAYS;
        }
        $forceRefresh = $request->shouldForceRefresh();

        // Get all metrics using MetricService
        foreach (FinancerMetricType::activeValues() as $metricType) {
            try {
                $metric = $this->metricService->getMetric(
                    $financerId,
                    $metricType,
                    $parameters,
                    $forceRefresh
                );
                $allMetrics[$metricType] = new IMetricResource($metric);
            } catch (Exception $e) {
                // Log error but continue with other metrics
                Log::error("Failed to fetch metric {$metricType}: ".$e->getMessage());
            }
        }

        return response()->json($allMetrics);
    }

    /**
     * Get comprehensive dashboard metrics for a financer.
     *
     * Returns a complete overview of financer metrics including active beneficiaries,
     * activation rates, session times, and HR communications in IMetric format.
     *
     * @response AllMetricsResource
     */
    #[RequiresPermission(PermissionDefaults::VIEW_FINANCER_METRICS)]
    #[QueryParameter('period', description: 'Période de temps pour le calcul des métriques.', type: 'string', example: '30d')]
    public function dashboard(FinancerMetricsRequest $request): JsonResponse
    {
        $financerId = $this->getValidFinancerId();

        if ($financerId === null) {
            return response()->json(['error' => 'No active financer ID found'], 400);
        }
        $parameters = $request->validated();
        if (! array_key_exists('period', $parameters)) {
            $parameters['period'] = MetricPeriod::THIRTY_DAYS;
        }
        $metrics = $this->metricService->getDashboardMetrics(
            $financerId,
            $parameters
        );

        return response()->json(
            new AllMetricsResource($metrics)
        );
    }

    /**
     * Get specific metric for a financer.
     *
     * Returns individual metric data in IMetric format.
     *
     * @response IMetricResource
     */
    #[RequiresPermission(PermissionDefaults::VIEW_FINANCER_METRICS)]
    #[QueryParameter('period', description: 'Période de temps pour le calcul des métriques.', type: 'string', example: '7d')]
    #[QueryParameter('refresh', description: 'Force le recalcul des métriques en ignorant le cache.', type: 'boolean', example: 'true')]
    public function getMetric(FinancerMetricsRequest $request, string $metricType): JsonResponse
    {
        $financerId = $this->getValidFinancerId();

        if ($financerId === null) {
            return response()->json(['error' => 'No active financer ID found'], 400);
        }
        // Validate metric type
        $validMetrics = FinancerMetricType::activeValues();

        if (! in_array($metricType, $validMetrics, true)) {
            return response()->json([
                'error' => 'Invalid metric type',
                'valid_types' => $validMetrics,
            ], 404);
        }

        $parameters = $request->validated();
        if (! array_key_exists('period', $parameters)) {
            $parameters['period'] = MetricPeriod::THIRTY_DAYS;
        }
        $forceRefresh = $request->shouldForceRefresh();
        $metric = $this->metricService->getMetric(
            $financerId,
            $metricType,
            $parameters,
            $forceRefresh
        );

        return response()->json(
            new IMetricResource($metric)
        );
    }

    /**
     * Helper method to get a valid string financer ID
     */
    private function getValidFinancerId(): ?string
    {
        $financerId = activeFinancerID();

        if (in_array($financerId, [null, '', '0'], true)) {
            return null;
        }

        // Handle array case - take first financer ID
        if (is_array($financerId)) {
            $financerId = $financerId[0] ?? '';
            if ($financerId === '') {
                return null;
            }
        }

        return $financerId;
    }
}
