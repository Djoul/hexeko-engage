<?php

use App\Enums\FinancerMetricType;
use App\Enums\MetricPeriod;
use App\Services\Metrics\Calculators\ActivationRateCalculator;
use App\Services\Metrics\Calculators\ActiveBeneficiariesCalculator;
use App\Services\Metrics\Calculators\ArticleReactionsCalculator;
use App\Services\Metrics\Calculators\ArticlesPerEmployeeCalculator;
use App\Services\Metrics\Calculators\BounceRateCalculator;
use App\Services\Metrics\Calculators\HrCommunicationsCalculator;
use App\Services\Metrics\Calculators\ModuleUsageCalculator;
use App\Services\Metrics\Calculators\SessionTimeCalculator;
use App\Services\Metrics\Calculators\ShortcutsClicksCalculator;
use App\Services\Metrics\Calculators\VoucherAverageAmountCalculator;
use App\Services\Metrics\Calculators\VoucherPurchasesCalculator;

return [
    /*
    |--------------------------------------------------------------------------
    | Metric Calculators Mapping
    |--------------------------------------------------------------------------
    |
    | This configuration maps metric types to their calculator implementations.
    | You can override or extend this mapping to add custom calculators.
    |
    */
    'calculators' => [
        FinancerMetricType::ACTIVE_BENEFICIARIES => ActiveBeneficiariesCalculator::class,
        FinancerMetricType::ACTIVATION_RATE => ActivationRateCalculator::class,
        FinancerMetricType::SESSION_TIME => SessionTimeCalculator::class,
        FinancerMetricType::MODULE_USAGE => ModuleUsageCalculator::class,
        FinancerMetricType::ARTICLE_VIEWED => HrCommunicationsCalculator::class,
        FinancerMetricType::VOUCHER_PURCHASES => VoucherPurchasesCalculator::class,
        FinancerMetricType::SHORTCUTS_CLICKS => ShortcutsClicksCalculator::class,
        FinancerMetricType::ARTICLE_REACTIONS => ArticleReactionsCalculator::class,
        FinancerMetricType::ARTICLES_PER_EMPLOYEE => ArticlesPerEmployeeCalculator::class,
        FinancerMetricType::BOUNCE_RATE => BounceRateCalculator::class,
        FinancerMetricType::VOUCHER_AVERAGE_AMOUNT => VoucherAverageAmountCalculator::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Disabled Metrics
    |--------------------------------------------------------------------------
    |
    | Configure which metrics should be disabled globally. You can list them
    | here or provide a comma-separated list via FINANCER_DISABLED_METRICS.
    |
    */
    'disabled_metrics' => (static function (): array {
        $baseDisabled = [
            FinancerMetricType::SESSION_TIME,
        ];

        $envDisabled = array_values(array_filter(array_map(
            static fn (string $metric): string => trim($metric),
            explode(',', (string) env('FINANCER_DISABLED_METRICS', ''))
        )));

        return array_values(array_filter(array_unique(array_merge($baseDisabled, $envDisabled))));
    })(),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure cache settings for metrics including TTL and driver.
    |
    */
    'cache' => [
        'ttl' => env('METRICS_CACHE_TTL', 3600), // 1 hour default
        'driver' => env('METRICS_CACHE_DRIVER', 'redis'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Period Definitions
    |--------------------------------------------------------------------------
    |
    | Define the available periods and their characteristics.
    |
    */
    'periods' => [
        MetricPeriod::SEVEN_DAYS => [
            'days' => 7,
            'interval' => 'daily',
            'cache_ttl' => 3600, // 1 hour
            'description' => '7 derniers jours',
        ],
        MetricPeriod::THIRTY_DAYS => [
            'days' => 30,
            'interval' => 'daily',
            'cache_ttl' => 7200, // 2 hours
            'description' => '30 derniers jours',
        ],
        MetricPeriod::THREE_MONTHS => [
            'months' => 3,
            'interval' => 'weekly',
            'cache_ttl' => 14400, // 4 hours
            'description' => '3 derniers mois',
        ],
        MetricPeriod::SIX_MONTHS => [
            'months' => 6,
            'interval' => 'monthly',
            'cache_ttl' => 28800, // 8 hours
            'description' => '6 derniers mois',
        ],
        MetricPeriod::TWELVE_MONTHS => [
            'months' => 12,
            'interval' => 'monthly',
            'cache_ttl' => 86400, // 24 hours
            'description' => '12 derniers mois',
        ],
        MetricPeriod::CUSTOM => [
            'interval' => 'flexible',
            'cache_ttl' => 3600, // 1 hour
            'description' => 'Période personnalisée',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which metrics appear on the dashboard by default.
    |
    */
    'dashboard_metrics' => [
        FinancerMetricType::ACTIVE_BENEFICIARIES,
        FinancerMetricType::ACTIVATION_RATE,
        FinancerMetricType::SESSION_TIME,
        FinancerMetricType::ARTICLE_VIEWED,
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch Processing
    |--------------------------------------------------------------------------
    |
    | Configure batch processing for metric generation.
    |
    */
    'batch' => [
        'chunk_size' => env('METRICS_BATCH_SIZE', 100),
        'queue' => env('METRICS_QUEUE', 'metrics'),
        'timeout' => env('METRICS_JOB_TIMEOUT', 300), // 5 minutes
    ],
];
