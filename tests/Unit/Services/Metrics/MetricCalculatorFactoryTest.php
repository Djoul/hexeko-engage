<?php

namespace Tests\Unit\Services\Metrics;

use App\Enums\FinancerMetricType;
use App\Exceptions\InvalidMetricTypeException;
use App\Services\Metrics\Calculators\ActivationRateCalculator;
use App\Services\Metrics\Calculators\ActiveBeneficiariesCalculator;
use App\Services\Metrics\Contracts\MetricCalculatorInterface;
use App\Services\Metrics\MetricCalculatorFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[Group('metrics')]
class MetricCalculatorFactoryTest extends TestCase
{
    private MetricCalculatorFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new MetricCalculatorFactory;
    }

    #[Test]
    public function it_creates_active_beneficiaries_calculator(): void
    {
        $calculator = $this->factory->make(FinancerMetricType::ACTIVE_BENEFICIARIES);

        $this->assertInstanceOf(MetricCalculatorInterface::class, $calculator);
        $this->assertInstanceOf(ActiveBeneficiariesCalculator::class, $calculator);
    }

    #[Test]
    public function it_creates_activation_rate_calculator(): void
    {
        $calculator = $this->factory->make(FinancerMetricType::ACTIVATION_RATE);

        $this->assertInstanceOf(MetricCalculatorInterface::class, $calculator);
        $this->assertInstanceOf(ActivationRateCalculator::class, $calculator);
    }

    #[Test]
    public function it_throws_exception_for_invalid_metric_type(): void
    {
        $this->expectException(InvalidMetricTypeException::class);
        $this->expectExceptionMessage('Invalid metric type: invalid_metric');

        $this->factory->make('invalid_metric');
    }

    #[Test]
    public function it_returns_same_instance_for_same_metric_type(): void
    {
        $calculator1 = $this->factory->make(FinancerMetricType::ACTIVE_BENEFICIARIES);
        $calculator2 = $this->factory->make(FinancerMetricType::ACTIVE_BENEFICIARIES);

        $this->assertSame($calculator1, $calculator2);
    }

    #[Test]
    public function it_supports_all_metric_types(): void
    {
        $metricTypes = [
            FinancerMetricType::ACTIVE_BENEFICIARIES,
            FinancerMetricType::ACTIVATION_RATE,
            FinancerMetricType::SESSION_TIME,
            FinancerMetricType::MODULE_USAGE,
            FinancerMetricType::ARTICLE_VIEWED,
            FinancerMetricType::VOUCHER_PURCHASES,
            FinancerMetricType::SHORTCUTS_CLICKS,
            FinancerMetricType::ARTICLE_REACTIONS,
            FinancerMetricType::ARTICLES_PER_EMPLOYEE,
            FinancerMetricType::BOUNCE_RATE,
            FinancerMetricType::VOUCHER_AVERAGE_AMOUNT,
        ];

        foreach ($metricTypes as $metricType) {
            $calculator = $this->factory->make($metricType);
            $this->assertInstanceOf(MetricCalculatorInterface::class, $calculator);
            $this->assertEquals($metricType, $calculator->getMetricType());
        }
    }

    #[Test]
    public function it_has_method_to_get_all_calculators(): void
    {
        $calculators = $this->factory->getAllCalculators();

        $this->assertIsArray($calculators);
        $this->assertCount(11, $calculators); // All 11 metric types

        foreach ($calculators as $metricType => $calculator) {
            $this->assertIsString($metricType);
            $this->assertInstanceOf(MetricCalculatorInterface::class, $calculator);
            $this->assertEquals($metricType, $calculator->getMetricType());
        }
    }

    #[Test]
    public function it_can_check_if_metric_type_is_supported(): void
    {
        $this->assertTrue($this->factory->supports(FinancerMetricType::ACTIVE_BENEFICIARIES));
        $this->assertTrue($this->factory->supports(FinancerMetricType::ACTIVATION_RATE));
        $this->assertFalse($this->factory->supports('non_existent_metric'));
    }

    #[Test]
    public function it_loads_calculators_from_config(): void
    {
        // Mock config
        config([
            'metrics.calculators' => [
                FinancerMetricType::ACTIVE_BENEFICIARIES => ActiveBeneficiariesCalculator::class,
                FinancerMetricType::ACTIVATION_RATE => ActivationRateCalculator::class,
            ],
        ]);

        $factory = new MetricCalculatorFactory;

        $this->assertTrue($factory->supports(FinancerMetricType::ACTIVE_BENEFICIARIES));
        $this->assertTrue($factory->supports(FinancerMetricType::ACTIVATION_RATE));
        // reset config to avoid leaking into other tests
        config(['metrics.calculators' => []]);
    }

    #[Test]
    public function it_resolves_calculators_from_container(): void
    {
        // Bind a mock calculator to the container
        $mockCalculator = $this->createMock(MetricCalculatorInterface::class);
        $mockCalculator->method('getMetricType')->willReturn('test_metric');

        $this->app->bind(ActivationRateCalculator::class, function () use ($mockCalculator): MockObject {
            return $mockCalculator;
        });

        config([
            'metrics.calculators' => [
                'test_metric' => ActivationRateCalculator::class,
            ],
        ]);

        $factory = new MetricCalculatorFactory;
        $calculator = $factory->make('test_metric');

        $this->assertSame($mockCalculator, $calculator);

        // reset config to avoid leaking into other tests
        config(['metrics.calculators' => []]);
    }
}
