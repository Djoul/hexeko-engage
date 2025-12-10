<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use App\DTOs\Financer\AllMetricsDTO;
use App\DTOs\Financer\IMetricDTO;
use App\Enums\FinancerMetricType;
use App\Enums\MetricPeriod;
use App\Exceptions\InvalidMetricTypeException;
use App\Models\FinancerMetric;
use App\Services\Metrics\Contracts\MetricCalculatorInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class MetricService
{
    public function __construct(
        private readonly MetricCalculatorFactory $calculatorFactory
    ) {}

    /**
     * Get metric data with 3-level cache cascade
     *
     * Level 1: Redis cache (TTL: 1h, ultra-fast ~1ms)
     * Level 2: Database (engagement_metrics table, pre-calculated data < 24h old)
     * Level 3: Real-time calculation (Calculator)
     *
     * @param  bool  $forceRefresh  Skip all caching and force recalculation
     * @return array<string, mixed>
     */
    private function getMetricDataWithCascade(
        string $financerId,
        string $metricType,
        Carbon $startDate,
        Carbon $endDate,
        string $period,
        MetricCalculatorInterface $calculator,
        bool $forceRefresh = false
    ): array {
        // FORCE REFRESH: Skip all caching and recalculate
        if ($forceRefresh) {
            return $calculator->calculate($financerId, $startDate, $endDate, $period);
        }

        $cacheKey = $calculator->getCacheKey($financerId, $startDate, $endDate, $period);

        // NIVEAU 1: Cache Redis (1 hour TTL)
        /** @var array<string, mixed> $cachedData */
        $cachedData = Cache::remember($cacheKey, $calculator->getCacheTTL(), function () use (
            $financerId,
            $metricType,
            $startDate,
            $endDate,
            $period,
            $calculator
        ): array {
            // NIVEAU 2: Base de données (engagement_metrics)
            $storedMetric = FinancerMetric::where('financer_id', $financerId)
                ->where('metric', str_replace('-', '_', "financer_{$metricType}"))
                ->where('period', $period)
//                ->where('date_from', $startDate->toDateString())
//                ->where('date_to', $endDate->toDateString())
                ->latest()
                ->first();

            // Check if data exists and is not too old (< 24h)
            if ($storedMetric && $storedMetric->updated_at->gt(now()->subHours(24))) {
                $data = $storedMetric->data;

                // Ensure we return an array
                return is_array($data) ? $data : [];
            }

            // NIVEAU 3: Calcul en temps réel
            return $calculator->calculate($financerId, $startDate, $endDate, $period);
        });

        return $cachedData;
    }

    /**
     * Get dashboard metrics for a financer
     *
     * @param  array<string, mixed>  $parameters
     */
    public function getDashboardMetrics(string $financerId, array $parameters): AllMetricsDTO
    {
        $dateRange = $this->getDateRangeFromParameters($parameters);

        $activeBeneficiaries = $this->getActiveBeneficiariesIMetric($financerId, $dateRange['start'], $dateRange['end']);
        $activationRate = $this->getActivationRateIMetric($financerId, $dateRange['start'], $dateRange['end']);
        $averageSessionTime = $this->getAverageSessionTimeIMetric($financerId, $dateRange['start'], $dateRange['end']);
        $hrCommunicationsViews = $this->getHrCommunicationsViewsIMetric($financerId, $dateRange['start'], $dateRange['end']);

        return new AllMetricsDTO(
            active_beneficiaries: $activeBeneficiaries,
            activation_rate: $activationRate,
            average_session_time: $averageSessionTime,
            article_viewed_views: $hrCommunicationsViews
        );
    }

    /**
     * Get individual metric
     *
     * @param  array<string, mixed>  $parameters
     * @param  bool  $forceRefresh  Skip cache and force recalculation
     */
    public function getMetric(string $financerId, string $metricType, array $parameters, bool $forceRefresh = false): IMetricDTO
    {
        // Validate metric type
        if (! FinancerMetricType::isActive($metricType)) {
            throw new InvalidMetricTypeException("Unknown metric type: {$metricType}");
        }

        $dateRange = $this->getDateRangeFromParameters($parameters);

        return match ($metricType) {
            FinancerMetricType::ACTIVE_BENEFICIARIES => $this->getActiveBeneficiariesIMetric(
                $financerId,
                $dateRange['start'],
                $dateRange['end'],
                is_string($parameters['period'] ?? null) ? $parameters['period'] : MetricPeriod::THIRTY_DAYS,
                $forceRefresh
            ),
            FinancerMetricType::ACTIVATION_RATE => $this->getActivationRateIMetric(
                $financerId,
                $dateRange['start'],
                $dateRange['end'],
                is_string($parameters['period'] ?? null) ? $parameters['period'] : MetricPeriod::THIRTY_DAYS,
                $forceRefresh
            ),
            FinancerMetricType::SESSION_TIME => $this->getAverageSessionTimeIMetric(
                $financerId,
                $dateRange['start'],
                $dateRange['end'],
                is_string($parameters['period'] ?? null) ? $parameters['period'] : MetricPeriod::THIRTY_DAYS,
                $forceRefresh
            ),
            FinancerMetricType::ARTICLE_VIEWED => $this->getHrCommunicationsViewsIMetric(
                $financerId,
                $dateRange['start'],
                $dateRange['end'],
                is_string($parameters['period'] ?? null) ? $parameters['period'] : MetricPeriod::THIRTY_DAYS,
                $forceRefresh
            ),
            FinancerMetricType::MODULE_USAGE => $this->getModuleUsageIMetric(
                $financerId,
                $dateRange['start'],
                $dateRange['end'],
                is_string($parameters['period'] ?? null) ? $parameters['period'] : MetricPeriod::THIRTY_DAYS,
                $forceRefresh
            ),
            FinancerMetricType::VOUCHER_PURCHASES => $this->getVoucherPurchasesIMetric(
                $financerId,
                $dateRange['start'],
                $dateRange['end'],
                is_string($parameters['period'] ?? null) ? $parameters['period'] : MetricPeriod::THIRTY_DAYS,
                $forceRefresh
            ),
            FinancerMetricType::SHORTCUTS_CLICKS => $this->getShortcutsClicksIMetric(
                $financerId,
                $dateRange['start'],
                $dateRange['end'],
                is_string($parameters['period'] ?? null) ? $parameters['period'] : MetricPeriod::THIRTY_DAYS,
                $forceRefresh
            ),
            FinancerMetricType::ARTICLE_REACTIONS => $this->getArticleReactionsIMetric(
                $financerId,
                $dateRange['start'],
                $dateRange['end'],
                is_string($parameters['period'] ?? null) ? $parameters['period'] : MetricPeriod::THIRTY_DAYS,
                $forceRefresh
            ),
            FinancerMetricType::ARTICLES_PER_EMPLOYEE => $this->getArticlesPerEmployeeIMetric(
                $financerId,
                $dateRange['start'],
                $dateRange['end'],
                is_string($parameters['period'] ?? null) ? $parameters['period'] : MetricPeriod::THIRTY_DAYS,
                $forceRefresh
            ),
            FinancerMetricType::BOUNCE_RATE => $this->getBounceRateIMetric(
                $financerId,
                $dateRange['start'],
                $dateRange['end'],
                is_string($parameters['period'] ?? null) ? $parameters['period'] : MetricPeriod::THIRTY_DAYS,
                $forceRefresh
            ),
            FinancerMetricType::VOUCHER_AVERAGE_AMOUNT => $this->getVoucherAverageAmountIMetric(
                $financerId,
                $dateRange['start'],
                $dateRange['end'],
                is_string($parameters['period'] ?? null) ? $parameters['period'] : MetricPeriod::THIRTY_DAYS,
                $forceRefresh
            ),
            default => throw new InvalidMetricTypeException("Unknown metric type: {$metricType}"),
        };

    }

    /**
     * Transform active beneficiaries data to IMetric format
     */
    private function getActiveBeneficiariesIMetric(string $financerId, Carbon $startDate, Carbon $endDate, string $period = MetricPeriod::THIRTY_DAYS, bool $forceRefresh = false): IMetricDTO
    {
        $calculator = $this->calculatorFactory->make(FinancerMetricType::ACTIVE_BENEFICIARIES);

        /** @var array{daily: array<int, array{date: string, count: int}>, total: int} $data */
        $data = $this->getMetricDataWithCascade(
            $financerId,
            FinancerMetricType::ACTIVE_BENEFICIARIES,
            $startDate,
            $endDate,
            $period,
            $calculator,
            $forceRefresh
        );

        $labels = [];
        $values = [];

        foreach ($data['daily'] as $dayData) {
            $labels[] = $this->formatDateLabel($dayData['date']);
            $values[] = $dayData['count'];
        }

        return IMetricDTO::createSimple(
            title: 'metrics.title.active-beneficiaries',
            tooltip: 'metrics.tooltip.active-beneficiaries',
            value: $data['total'],
            labels: $this->ensureStringKeysForMetric($labels),
            data: $this->ensureStringKeysForMetric($values),
        );
    }

    /**
     * Transform activation rate data to IMetric format
     */
    private function getActivationRateIMetric(string $financerId, Carbon $startDate, Carbon $endDate, string $period = MetricPeriod::THIRTY_DAYS, bool $forceRefresh = false): IMetricDTO
    {
        $calculator = $this->calculatorFactory->make(FinancerMetricType::ACTIVATION_RATE);

        /** @var array{daily?: array<int, array{date: string, count: int}>, rate?: float, total?: int} $data */
        $data = $this->getMetricDataWithCascade(
            $financerId,
            FinancerMetricType::ACTIVATION_RATE,
            $startDate,
            $endDate,
            $period,
            $calculator,
            $forceRefresh
        );

        // Check if we have the new structure with 'daily' key
        if (array_key_exists('daily', $data) && is_array($data['daily'])) {
            $labels = [];
            $values = [];

            foreach ($data['daily'] as $dayData) {
                $labels[] = $this->formatDateLabel($dayData['date']);
                $values[] = $dayData['count'];
            }

            // Calculate the average of all daily values
            $average = count($values) > 0
                ? round(array_sum($values) / count($values), 1)
                : 0;

            return IMetricDTO::createSimple(
                title: 'metrics.title.activation-rate',
                tooltip: 'metrics.tooltip.activation-rate',
                value: (string) $average,
                labels: $this->ensureStringKeysForMetric($labels),
                data: $this->ensureStringKeysForMetric($values),
                unit: 'metrics.unit.percentage'
            );
        }

        // Fallback for old structure (can be removed after cache is cleared)
        return IMetricDTO::createSimple(
            title: 'metrics.title.activation-rate',
            tooltip: 'metrics.tooltip.activation-rate',
            value: (string) ($data['rate'] ?? 0),
            labels: $this->ensureStringKeysForMetric(['metrics.label.rate']),
            data: $this->ensureStringKeysForMetric([$data['rate'] ?? 0]),
            unit: 'metrics.unit.percentage'
        );
    }

    /**
     * Transform session time data to IMetric format
     */
    private function getAverageSessionTimeIMetric(string $financerId, Carbon $startDate, Carbon $endDate, string $period = MetricPeriod::THIRTY_DAYS, bool $forceRefresh = false): IMetricDTO
    {
        $calculator = $this->calculatorFactory->make(FinancerMetricType::SESSION_TIME);

        /** @var array{daily?: array<int, array{date: string, count: int}>, total?: int, median_minutes?: int} $data */
        $data = $this->getMetricDataWithCascade(
            $financerId,
            FinancerMetricType::SESSION_TIME,
            $startDate,
            $endDate,
            $period,
            $calculator,
            $forceRefresh
        );

        // Check if we have the new structure with 'daily' key
        if (array_key_exists('daily', $data) && is_array($data['daily'])) {
            $labels = [];
            $values = [];

            foreach ($data['daily'] as $dayData) {
                $labels[] = $this->formatDateLabel($dayData['date']);
                $values[] = $dayData['count'];
            }

            $totalMinutes = (int) ($data['total'] ?? 0);

            return IMetricDTO::createSimple(
                title: 'metrics.title.average-session-time',
                tooltip: 'metrics.tooltip.average-session-time',
                value: $this->formatMinutesToDuration($totalMinutes),
                labels: $this->ensureStringKeysForMetric($labels),
                data: $this->ensureStringKeysForMetric($values),
            );
        }

        // Fallback for old structure (can be removed after cache is cleared)
        $medianMinutes = (int) ($data['median_minutes'] ?? 0);

        return IMetricDTO::createSimple(
            title: 'metrics.title.average-session-time',
            tooltip: 'metrics.tooltip.average-session-time',
            value: $this->formatMinutesToDuration($medianMinutes),
            labels: $this->ensureStringKeysForMetric(['metrics.label.median']),
            data: $this->ensureStringKeysForMetric([$data['median_minutes'] ?? 0]),
        );
    }

    /**
     * Transform HR communications data to IMetric format
     */
    private function getHrCommunicationsViewsIMetric(string $financerId, Carbon $startDate, Carbon $endDate, string $period = MetricPeriod::THIRTY_DAYS, bool $forceRefresh = false): IMetricDTO
    {
        $calculator = $this->calculatorFactory->make(FinancerMetricType::ARTICLE_VIEWED);

        /** @var array{daily?: array<int, array{date: string, count: int}>, total?: int, total_interactions?: int, articles?: array{views: int}, tools?: array{clicks: int}} $data */
        $data = $this->getMetricDataWithCascade(
            $financerId,
            FinancerMetricType::ARTICLE_VIEWED,
            $startDate,
            $endDate,
            $period,
            $calculator,
            $forceRefresh
        );

        // Check if we have the new structure with 'daily' key
        if (array_key_exists('daily', $data) && is_array($data['daily'])) {
            $labels = [];
            $values = [];

            foreach ($data['daily'] as $dayData) {
                $labels[] = $this->formatDateLabel($dayData['date']);
                $values[] = $dayData['count'];
            }

            return IMetricDTO::createSimple(
                title: 'metrics.title.articles-viewed',
                tooltip: 'metrics.tooltip.articles-viewed',
                value: $data['total'] ?? 0,
                labels: $this->ensureStringKeysForMetric($labels),
                data: $this->ensureStringKeysForMetric($values),
                unit: 'metrics.unit.views'
            );
        }

        // Fallback for old structure
        return IMetricDTO::createSimple(
            title: 'metrics.title.articles-viewed',
            tooltip: 'metrics.tooltip.articles-viewed',
            value: $data['total_interactions'] ?? 0,
            labels: $this->ensureStringKeysForMetric(['metrics.label.articles', 'metrics.label.tools']),
            data: $this->ensureStringKeysForMetric([$data['articles']['views'] ?? 0, $data['tools']['clicks'] ?? 0]),
            unit: 'metrics.unit.views'
        );
    }

    /**
     * Transform module usage data to IMetric format
     */
    private function getModuleUsageIMetric(string $financerId, Carbon $startDate, Carbon $endDate, string $period = MetricPeriod::THIRTY_DAYS, bool $forceRefresh = false): IMetricDTO
    {
        $calculator = $this->calculatorFactory->make(FinancerMetricType::MODULE_USAGE);

        /** @var array{daily?: array<int, array{date: string, modules: array<string, int>}>, total?: int, moduleNames?: array<string, object>} $data */
        $data = $this->getMetricDataWithCascade(
            $financerId,
            FinancerMetricType::MODULE_USAGE,
            $startDate,
            $endDate,
            $period,
            $calculator,
            $forceRefresh
        );

        // Check if we have the new structure with 'daily' key
        if (array_key_exists('daily', $data) && is_array($data['daily'])) {
            $labels = [];
            $moduleData = [];
            $allModules = [];
            $moduleObjects = $data['moduleNames'] ?? [];

            // First pass: collect all modules and dates
            foreach ($data['daily'] as $dayData) {
                $labels[] = $this->formatDateLabel($dayData['date']);
                foreach ($dayData['modules'] as $moduleId => $count) {
                    $allModules[$moduleId] = true;
                }
            }

            // Get sorted list of all module IDs
            $moduleIds = array_keys($allModules);

            // Sort modules by name
            usort($moduleIds, function ($a, $b) use ($moduleObjects): int {
                $objA = $moduleObjects[$a] ?? null;
                $objB = $moduleObjects[$b] ?? null;

                $nameA = '';
                if (is_object($objA) && property_exists($objA, 'name')) {
                    $nameA = is_string($objA->name) ? $objA->name : '';
                }

                $nameB = '';
                if (is_object($objB) && property_exists($objB, 'name')) {
                    $nameB = is_string($objB->name) ? $objB->name : '';
                }

                return strcasecmp($nameA, $nameB);
            });

            // Initialize data arrays for each module
            foreach ($moduleIds as $moduleId) {
                $moduleData[$moduleId] = [];
            }

            // Second pass: fill in the data
            foreach ($data['daily'] as $dayData) {
                foreach ($moduleIds as $moduleId) {
                    $moduleData[$moduleId][] = $dayData['modules'][$moduleId] ?? 0;
                }
            }

            // Build datasets
            $datasets = [];
            // Handle both Collection objects (from Calculator) and arrays (from DB/Cache deserialization)
            $moduleObjectsArray = is_object($moduleObjects) && method_exists($moduleObjects, 'toArray')
                ? $moduleObjects->toArray()
                : (is_array($moduleObjects) ? $moduleObjects : []);

            foreach ($moduleIds as $moduleId) {
                if (array_key_exists($moduleId, $moduleObjectsArray)) {
                    $module = $moduleObjectsArray[$moduleId];

                    // Module can be an object or array depending on source (Collection vs JSON)
                    if (is_object($module)) {
                        $nameData = property_exists($module, 'name') ? $module->name : null;
                    } else {
                        $nameData = is_array($module) && array_key_exists('name', $module) ? $module['name'] : null;
                    }

                    // Generate translation key from English module name
                    $translationKey = $this->generateModuleTranslationKey($nameData, $moduleId);

                    $datasets[] = [
                        'label' => $translationKey,
                        'data' => $moduleData[$moduleId],
                    ];
                }
            }

            return IMetricDTO::createMultiple(
                title: 'metrics.title.module-usage',
                tooltip: 'metrics.tooltip.module-usage',
                value: (int) ($data['total'] ?? 0),
                labels: $this->ensureStringKeysForMetric($labels),
                datasets: $this->ensureStringKeysForMetric($datasets),
                unit: 'metrics.unit.uses'
            );
        }

        // Fallback for old structure
        $modules = [];
        $totalUses = 0;

        foreach ($data as $moduleName => $stats) {
            if (is_array($stats) && array_key_exists('total_uses', $stats)) {
                $modules[] = [
                    'name' => $moduleName,
                    'unique_users' => $stats['unique_users'] ?? 0,
                    'total_uses' => $stats['total_uses'] ?? 0,
                ];
                $totalUses += is_numeric($stats['total_uses'] ?? 0) ? (int) ($stats['total_uses'] ?? 0) : 0;
            }
        }

        $labels = [];
        $data = [];

        foreach ($modules as $module) {
            $labels[] = $module['name'];
            $data[] = $module['total_uses'];
        }

        return IMetricDTO::createMultiple(
            title: 'metrics.title.module-usage',
            tooltip: 'metrics.tooltip.module-usage',
            value: $totalUses,
            labels: $this->ensureStringKeysForMetric($labels),
            datasets: $this->ensureStringKeysForMetric([
                [
                    'label' => 'metrics.label.uses',
                    'data' => $data,
                ],
            ]),
            unit: 'metrics.unit.uses'
        );
    }

    /**
     * Transform voucher purchases data to IMetric format
     */
    private function getVoucherPurchasesIMetric(string $financerId, Carbon $startDate, Carbon $endDate, string $period = MetricPeriod::THIRTY_DAYS, bool $forceRefresh = false): IMetricDTO
    {
        $calculator = $this->calculatorFactory->make(FinancerMetricType::VOUCHER_PURCHASES);

        /** @var array{daily?: array<int, array{date: string, count: int}>, total?: int, total_volume?: int, total_purchases?: int} $data */
        $data = $this->getMetricDataWithCascade(
            $financerId,
            FinancerMetricType::VOUCHER_PURCHASES,
            $startDate,
            $endDate,
            $period,
            $calculator,
            $forceRefresh
        );

        // Check if we have the new structure with 'daily' key
        if (array_key_exists('daily', $data) && is_array($data['daily'])) {
            $labels = [];
            $values = [];

            foreach ($data['daily'] as $dayData) {
                $labels[] = $this->formatDateLabel($dayData['date']);
                // Count is already a number, no conversion needed
                $values[] = $dayData['count'];
            }

            return IMetricDTO::createSimple(
                title: 'metrics.title.voucher-purchases',
                tooltip: 'metrics.tooltip.voucher-purchases',
                value: $data['total'] ?? 0,
                labels: $this->ensureStringKeysForMetric($labels),
                data: $this->ensureStringKeysForMetric($values),
                unit: 'metrics.unit.purchases'
            );
        }

        // Fallback for old format
        return IMetricDTO::createSimple(
            title: 'metrics.title.voucher-purchases',
            tooltip: 'metrics.tooltip.voucher-purchases',
            value: $data['total_purchases'] ?? 0,
            labels: $this->ensureStringKeysForMetric(['metrics.label.purchases']),
            data: $this->ensureStringKeysForMetric([$data['total_purchases'] ?? 0]),
            unit: 'metrics.unit.purchases'
        );
    }

    /**
     * Transform shortcuts clicks data to IMetric format
     */
    private function getShortcutsClicksIMetric(string $financerId, Carbon $startDate, Carbon $endDate, string $period = MetricPeriod::THIRTY_DAYS, bool $forceRefresh = false): IMetricDTO
    {
        $calculator = $this->calculatorFactory->make(FinancerMetricType::SHORTCUTS_CLICKS);

        /** @var array{daily?: array<int, array{date: string, count: int}>, shortcuts?: array<string, array{daily: array<int, array{count: int}>}>, total?: int} $data */
        $data = $this->getMetricDataWithCascade(
            $financerId,
            FinancerMetricType::SHORTCUTS_CLICKS,
            $startDate,
            $endDate,
            $period,
            $calculator,
            $forceRefresh
        );

        // Handle new daily format with multiple shortcuts
        if (array_key_exists('daily', $data) && array_key_exists('shortcuts', $data)) {
            $labels = [];
            $datasets = [];

            // Get labels from daily data (dates)
            foreach ($data['daily'] as $dayData) {
                $labels[] = $this->formatDateLabel($dayData['date']);
            }

            // Create a dataset for each shortcut
            foreach ($data['shortcuts'] as $shortcutName => $shortcutData) {
                $values = [];
                foreach ($shortcutData['daily'] as $day) {
                    $values[] = $day['count'];
                }

                $datasets[] = [
                    'label' => $shortcutName,
                    'data' => $values,
                ];
            }

            return IMetricDTO::createMultiple(
                title: 'metrics.title.shortcuts-clicks',
                tooltip: 'metrics.tooltip.shortcuts-clicks',
                value: $data['total'] ?? 0,
                labels: $this->ensureStringKeysForMetric($labels),
                datasets: $this->ensureStringKeysForMetric($datasets),
                unit: 'metrics.unit.clicks'
            );
        }

        // Fallback for old format
        $shortcuts = [];
        $totalClicks = 0;

        foreach ($data as $shortcutType => $stats) {
            if (is_array($stats) && array_key_exists('total_clicks', $stats)) {
                $shortcuts[] = [
                    'type' => $shortcutType,
                    'total_clicks' => $stats['total_clicks'],
                    'unique_users' => $stats['unique_users'] ?? 0,
                ];
                $totalClicks += (int) $stats['total_clicks'];
            }
        }

        $labels = [];
        $values = [];

        foreach ($shortcuts as $shortcut) {
            $labels[] = $shortcut['type'];
            $values[] = $shortcut['total_clicks'];
        }

        return IMetricDTO::createMultiple(
            title: 'metrics.title.shortcuts-clicks',
            tooltip: 'metrics.tooltip.shortcuts-clicks',
            value: $totalClicks,
            labels: $this->ensureStringKeysForMetric($labels),
            datasets: $this->ensureStringKeysForMetric([
                [
                    'label' => 'metrics.label.clicks',
                    'data' => $values,
                ],
            ]),
            unit: 'metrics.unit.clicks'
        );
    }

    /**
     * Transform article reactions data to IMetric format
     */
    private function getArticleReactionsIMetric(string $financerId, Carbon $startDate, Carbon $endDate, string $period = MetricPeriod::THIRTY_DAYS, bool $forceRefresh = false): IMetricDTO
    {
        $calculator = $this->calculatorFactory->make(FinancerMetricType::ARTICLE_REACTIONS);

        /** @var array{daily?: array<int, array{date: string, count: int}>, total?: int, total_reactions?: int} $data */
        $data = $this->getMetricDataWithCascade(
            $financerId,
            FinancerMetricType::ARTICLE_REACTIONS,
            $startDate,
            $endDate,
            $period,
            $calculator,
            $forceRefresh
        );

        // Handle daily format
        if (array_key_exists('daily', $data) && is_array($data['daily'])) {
            $labels = [];
            $values = [];

            foreach ($data['daily'] as $dayData) {
                $labels[] = $this->formatDateLabel($dayData['date']);
                $values[] = $dayData['count'];
            }

            return IMetricDTO::createSimple(
                title: 'metrics.title.article-reactions',
                tooltip: 'metrics.tooltip.article-reactions',
                value: $data['total'] ?? 0,
                labels: $this->ensureStringKeysForMetric($labels),
                data: $this->ensureStringKeysForMetric($values),
                unit: 'metrics.unit.reactions'
            );
        }

        // Fallback for old format
        return IMetricDTO::createSimple(
            title: 'metrics.title.article-reactions',
            tooltip: 'metrics.tooltip.article-reactions',
            value: $data['total_reactions'] ?? 0,
            labels: $this->ensureStringKeysForMetric(['metrics.label.total-reactions']),
            data: $this->ensureStringKeysForMetric([$data['total_reactions'] ?? 0]),
            unit: 'metrics.unit.reactions'
        );
    }

    /**
     * Transform articles per employee data to IMetric format
     */
    private function getArticlesPerEmployeeIMetric(string $financerId, Carbon $startDate, Carbon $endDate, string $period = MetricPeriod::THIRTY_DAYS, bool $forceRefresh = false): IMetricDTO
    {
        $calculator = $this->calculatorFactory->make(FinancerMetricType::ARTICLES_PER_EMPLOYEE);

        /** @var array{daily?: array<int, array{date: string, value: float}>, total?: float, articles_per_employee?: float} $data */
        $data = $this->getMetricDataWithCascade(
            $financerId,
            FinancerMetricType::ARTICLES_PER_EMPLOYEE,
            $startDate,
            $endDate,
            $period,
            $calculator,
            $forceRefresh
        );

        // Handle daily format
        if (array_key_exists('daily', $data) && is_array($data['daily'])) {
            $labels = [];
            $values = [];

            foreach ($data['daily'] as $dayData) {
                $labels[] = $this->formatDateLabel($dayData['date']);
                $values[] = $dayData['value'];
            }

            return IMetricDTO::createSimple(
                title: 'metrics.title.articles-per-employee',
                tooltip: 'metrics.tooltip.articles-per-employee',
                value: $data['total'] ?? 0,
                labels: $this->ensureStringKeysForMetric($labels),
                data: $this->ensureStringKeysForMetric($values),
                unit: 'metrics.unit.ratio'
            );
        }

        // Fallback for old format
        return IMetricDTO::createSimple(
            title: 'metrics.title.articles-per-employee',
            tooltip: 'metrics.tooltip.articles-per-employee',
            value: $data['articles_per_employee'] ?? 0,
            labels: $this->ensureStringKeysForMetric(['metrics.label.ratio']),
            data: $this->ensureStringKeysForMetric([$data['articles_per_employee'] ?? 0]),
            unit: 'metrics.unit.ratio'
        );
    }

    /**
     * Transform bounce rate data to IMetric format
     */
    private function getBounceRateIMetric(string $financerId, Carbon $startDate, Carbon $endDate, string $period = MetricPeriod::THIRTY_DAYS, bool $forceRefresh = false): IMetricDTO
    {
        $calculator = $this->calculatorFactory->make(FinancerMetricType::BOUNCE_RATE);

        /** @var array{bounce_rate: float} $data */
        $data = $this->getMetricDataWithCascade(
            $financerId,
            FinancerMetricType::BOUNCE_RATE,
            $startDate,
            $endDate,
            $period,
            $calculator,
            $forceRefresh
        );

        return IMetricDTO::createSimple(
            title: 'metrics.title.bounce-rate',
            tooltip: 'metrics.tooltip.bounce-rate',
            value: (string) $data['bounce_rate'],
            labels: $this->ensureStringKeysForMetric(['metrics.label.rate']),
            data: $this->ensureStringKeysForMetric([$data['bounce_rate']]),
            unit: 'metrics.unit.percentage'
        );
    }

    /**
     * Transform voucher average amount data to IMetric format
     */
    private function getVoucherAverageAmountIMetric(string $financerId, Carbon $startDate, Carbon $endDate, string $period = MetricPeriod::THIRTY_DAYS, bool $forceRefresh = false): IMetricDTO
    {
        $calculator = $this->calculatorFactory->make(FinancerMetricType::VOUCHER_AVERAGE_AMOUNT);

        /** @var array{daily?: array<int, array{date: string, count: int}>, total?: int, average_amount?: float} $data */
        $data = $this->getMetricDataWithCascade(
            $financerId,
            FinancerMetricType::VOUCHER_AVERAGE_AMOUNT,
            $startDate,
            $endDate,
            $period,
            $calculator,
            $forceRefresh
        );

        // Check if we have the new structure with 'daily' key
        if (array_key_exists('daily', $data) && is_array($data['daily'])) {
            $labels = [];
            $values = [];

            foreach ($data['daily'] as $dayData) {
                $labels[] = $this->formatDateLabel($dayData['date']);
                // Convert eurocents to euros for display
                $values[] = round((float) $dayData['count'] / 100, 2);
            }

            // Convert eurocents to euros for total value
            $totalInEuros = round((float) ($data['total'] ?? 0) / 100, 2);

            return IMetricDTO::createSimple(
                title: 'metrics.title.voucher-average-amount',
                tooltip: 'metrics.tooltip.voucher-average-amount',
                value: (string) $totalInEuros,
                labels: $this->ensureStringKeysForMetric($labels),
                data: $this->ensureStringKeysForMetric($values),
                unit: 'metrics.unit.currency'
            );
        }

        // Fallback for old format
        // Convert eurocents to euros for display
        $averageInEuros = round((float) ($data['average_amount'] ?? 0) / 100, 2);

        return IMetricDTO::createSimple(
            title: 'metrics.title.voucher-average-amount',
            tooltip: 'metrics.tooltip.voucher-average-amount',
            value: (string) $averageInEuros,
            labels: $this->ensureStringKeysForMetric(['metrics.label.average-amount']),
            data: $this->ensureStringKeysForMetric([$averageInEuros]),
            unit: 'metrics.unit.currency'
        );
    }

    /**
     * Get date range from parameters
     *
     * @param  array<string, mixed>  $parameters
     * @return array{start: Carbon, end: Carbon}
     */
    private function getDateRangeFromParameters(array $parameters): array
    {
        $period = $parameters['period'] ?? MetricPeriod::getDefault();

        // Validate period
        if (! in_array($period, MetricPeriod::getValidPeriods())) {
            throw new InvalidArgumentException('Invalid period: '.(is_string($period) ? $period : 'unknown'));
        }

        $endDate = now()->subDay()->endOfDay(); // https://hexeko.atlassian.net/browse/UE-332 Do not include the current day

        $startDate = match ($period) {
            MetricPeriod::SEVEN_DAYS => $endDate->copy()->subDays(6),
            MetricPeriod::THIRTY_DAYS => $endDate->copy()->subDays(29),
            MetricPeriod::THREE_MONTHS => $endDate->copy()->subMonths(3),
            MetricPeriod::SIX_MONTHS => $endDate->copy()->subMonths(6),
            MetricPeriod::TWELVE_MONTHS => $endDate->copy()->subYear(),
            default => $endDate->copy()->subDays(29),
        };

        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    /**
     * Format date label for display
     */
    private function formatDateLabel(string $date): string
    {
        return Carbon::parse($date)->format('d/m');
    }

    /**
     * Convert list array to string-mixed associative array for PHPStan
     *
     * @param  array<int, mixed>  $list
     * @return array<string, mixed>
     */
    private function ensureStringKeysForMetric(array $list): array
    {
        /** @var array<string, mixed> $result */
        $result = $list; // Trust the runtime that this is compatible

        return $result;
    }

    /**
     * Format minutes into a human-readable duration string
     */
    private function formatMinutesToDuration(int $minutes): string
    {
        if ($minutes === 0) {
            return '0 min';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours === 0) {
            return "{$remainingMinutes} min";
        }

        if ($remainingMinutes === 0) {
            return "{$hours} h";
        }

        return "{$hours} h {$remainingMinutes} min";
    }

    /**
     * Generate translation key from module name data
     *
     * Converts English module name to camelCase and returns translation key format
     *
     * @param  array<string, string>|string|null  $nameData
     */
    private function generateModuleTranslationKey(array|string|null $nameData, string $moduleId): string
    {
        $englishName = null;

        // Extract English name from translatable array or string
        if (is_array($nameData)) {
            // Priority: en-US, en-GB, en, then fallback to first available
            $englishName = $nameData['en-US'] ?? $nameData['en-GB'] ?? $nameData['en'] ?? null;

            // If no English variant found, try first available translation as fallback
            if ($englishName === null && count($nameData) > 0) {
                $englishName = array_values($nameData)[0];
            }
        } elseif (is_string($nameData)) {
            $englishName = $nameData;
        }

        // If still no name found, use fallback with module ID
        if ($englishName === null || $englishName === '') {
            return "interface.module.module{$moduleId}";
        }

        // Convert to camelCase
        // Remove special characters, split by spaces and hyphens
        $words = preg_split('/[\s\-_]+/', $englishName);

        if ($words === false || count($words) === 0) {
            return "interface.module.module{$moduleId}";
        }

        // First word lowercase, rest title case
        $camelCase = lcfirst(array_shift($words) ?? '');
        foreach ($words as $word) {
            $camelCase .= ucfirst(strtolower($word));
        }

        // Ensure only alphanumeric characters remain
        $camelCase = preg_replace('/[^a-zA-Z0-9]/', '', $camelCase);

        return "interface.module.{$camelCase}";
    }
}
