<?php

namespace App\Services\Metrics;

use App\Enums\FinancerMetricType;
use App\Exceptions\InvalidMetricTypeException;
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
use App\Services\Metrics\Contracts\MetricCalculatorInterface;
use Illuminate\Support\Facades\App;

class MetricCalculatorFactory
{
    /**
     * Singleton instances of calculators
     *
     * @var array<string, MetricCalculatorInterface>
     */
    private array $calculators = [];

    /**
     * Mapping of metric types to calculator classes
     *
     * @var array<string, class-string<MetricCalculatorInterface>>
     */
    private array $calculatorMap;

    public function __construct()
    {
        $this->calculatorMap = $this->getCalculatorMap();
    }

    /**
     * Create or get a calculator instance for the given metric type
     *
     * @throws InvalidMetricTypeException
     */
    public function make(string $metricType): MetricCalculatorInterface
    {
        if (! $this->supports($metricType)) {
            throw new InvalidMetricTypeException("Invalid metric type: {$metricType}");
        }

        // Return singleton instance
        if (! array_key_exists($metricType, $this->calculators)) {
            $calculatorClass = $this->calculatorMap[$metricType];
            $this->calculators[$metricType] = App::make($calculatorClass);
        }

        return $this->calculators[$metricType];
    }

    /**
     * Check if a metric type is supported
     */
    public function supports(string $metricType): bool
    {
        return array_key_exists($metricType, $this->calculatorMap);
    }

    /**
     * Get all available calculators
     *
     * @return array<string, MetricCalculatorInterface>
     */
    public function getAllCalculators(): array
    {
        $allCalculators = [];

        foreach (array_keys($this->calculatorMap) as $metricType) {
            $allCalculators[$metricType] = $this->make($metricType);
        }

        return $allCalculators;
    }

    /**
     * Get the calculator mapping
     *
     * @return array<string, class-string<MetricCalculatorInterface>>
     */
    private function getCalculatorMap(): array
    {
        // Check if mapping is defined in config
        /** @var array<string, class-string<MetricCalculatorInterface>>|mixed $configMap */
        $configMap = config('metrics.calculators', []);

        if (! empty($configMap) && is_array($configMap)) {
            /** @var array<string, class-string<MetricCalculatorInterface>> $validatedMap */
            $validatedMap = [];
            foreach ($configMap as $key => $value) {
                if (is_string($key) && is_string($value) && class_exists($value)) {
                    $validatedMap[$key] = $value;
                }
            }
            if (! empty($validatedMap)) {
                return $validatedMap;
            }
        }

        // Default mapping
        return [
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
        ];
    }
}
