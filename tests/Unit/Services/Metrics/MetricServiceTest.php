<?php

namespace Tests\Unit\Services\Metrics;

use App\DTOs\Financer\AllMetricsDTO;
use App\DTOs\Financer\IMetricDTO;
use App\Enums\FinancerMetricType;
use App\Enums\MetricPeriod;
use App\Exceptions\InvalidMetricTypeException;
use App\Models\Financer;
use App\Services\Metrics\Contracts\MetricCalculatorInterface;
use App\Services\Metrics\MetricCalculatorFactory;
use App\Services\Metrics\MetricService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('metrics')]
class MetricServiceTest extends TestCase
{
    use DatabaseTransactions;

    private MetricService $service;

    private MockInterface $factoryMock;

    private MockInterface $calculatorMock;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['metrics.disabled_metrics' => []]);

        $this->financer = Financer::factory()->create();

        $this->calculatorMock = Mockery::mock(MetricCalculatorInterface::class);
        $this->factoryMock = Mockery::mock(MetricCalculatorFactory::class);

        $this->service = new MetricService(
            $this->factoryMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_retrieves_active_beneficiaries_metric(): void
    {
        $metricType = FinancerMetricType::ACTIVE_BENEFICIARIES;
        $period = MetricPeriod::SEVEN_DAYS;
        $expectedData = [
            'total' => 100,
            'daily' => [
                ['date' => '2025-07-24', 'count' => 10],
                ['date' => '2025-07-25', 'count' => 15],
            ],
        ];

        $this->factoryMock
            ->shouldReceive('make')
            ->once()
            ->with($metricType)
            ->andReturn($this->calculatorMock);

        $this->calculatorMock
            ->shouldReceive('getCacheKey')
            ->once()
            ->andReturn('test-cache-key');

        $this->calculatorMock
            ->shouldReceive('getCacheTTL')
            ->once()
            ->andReturn(3600);

        $this->calculatorMock
            ->shouldReceive('calculate')
            ->once()
            ->andReturn($expectedData);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $result = $this->service->getMetric(
            $this->financer->id,
            $metricType,
            ['period' => $period]
        );

        $this->assertInstanceOf(IMetricDTO::class, $result);
    }

    #[Test]
    public function it_calculates_activation_rate_metric(): void
    {
        $metricType = FinancerMetricType::ACTIVATION_RATE;
        $period = MetricPeriod::THIRTY_DAYS;
        $expectedData = ['rate' => 85.0, 'activated' => 150, 'total' => 180];
        $cacheKey = 'metrics:activation_rate:'.$this->financer->id.':30d';
        $cacheTtl = 3600;

        $this->factoryMock
            ->shouldReceive('make')
            ->once()
            ->with($metricType)
            ->andReturn($this->calculatorMock);

        $this->calculatorMock
            ->shouldReceive('getCacheKey')
            ->once()
            ->andReturn($cacheKey);

        $this->calculatorMock
            ->shouldReceive('getCacheTTL')
            ->once()
            ->andReturn($cacheTtl);

        $this->calculatorMock
            ->shouldReceive('calculate')
            ->once()
            ->andReturn($expectedData);

        Cache::shouldReceive('remember')
            ->once()
            ->with($cacheKey, $cacheTtl, Mockery::type('callable'))
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $result = $this->service->getMetric(
            $this->financer->id,
            $metricType,
            ['period' => $period]
        );

        $this->assertInstanceOf(IMetricDTO::class, $result);
    }

    #[Test]
    public function it_handles_session_time_metric(): void
    {
        $metricType = FinancerMetricType::SESSION_TIME;
        $period = MetricPeriod::SEVEN_DAYS;
        $expectedData = ['average' => 1200, 'total' => 12000];

        $this->factoryMock
            ->shouldReceive('make')
            ->once()
            ->with($metricType)
            ->andReturn($this->calculatorMock);

        $this->calculatorMock
            ->shouldReceive('getCacheKey')
            ->once()
            ->andReturn('test-cache-key');

        $this->calculatorMock
            ->shouldReceive('getCacheTTL')
            ->once()
            ->andReturn(3600);

        $this->calculatorMock
            ->shouldReceive('calculate')
            ->once()
            ->andReturn($expectedData);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $result = $this->service->getMetric(
            $this->financer->id,
            $metricType,
            ['period' => $period]
        );

        $this->assertInstanceOf(IMetricDTO::class, $result);
    }

    #[Test]
    public function it_gets_dashboard_metrics(): void
    {
        $period = MetricPeriod::THIRTY_DAYS;

        $dashboardMetrics = [
            FinancerMetricType::ACTIVE_BENEFICIARIES,
            FinancerMetricType::ACTIVATION_RATE,
            FinancerMetricType::SESSION_TIME,
            FinancerMetricType::ARTICLE_VIEWED,
        ];

        $expectedResults = [
            FinancerMetricType::ACTIVE_BENEFICIARIES => ['total' => 100, 'daily' => []],
            FinancerMetricType::ACTIVATION_RATE => ['rate' => 85.0, 'activated' => 150, 'total' => 180],
            FinancerMetricType::SESSION_TIME => ['average' => 1200, 'total' => 12000],
            FinancerMetricType::ARTICLE_VIEWED => ['total' => 50, 'daily' => []],
        ];

        foreach ($dashboardMetrics as $metricType) {
            $calculator = Mockery::mock(MetricCalculatorInterface::class);

            $this->factoryMock
                ->shouldReceive('make')
                ->once()
                ->with($metricType)
                ->andReturn($calculator);

            $calculator
                ->shouldReceive('getCacheKey')
                ->once()
                ->andReturn("cache-key-{$metricType}");

            $calculator
                ->shouldReceive('getCacheTTL')
                ->once()
                ->andReturn(3600);

            $calculator
                ->shouldReceive('calculate')
                ->once()
                ->andReturn($expectedResults[$metricType]);
        }

        Cache::shouldReceive('remember')
            ->times(4)
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $result = $this->service->getDashboardMetrics(
            $this->financer->id,
            ['period' => $period]
        );

        $this->assertInstanceOf(AllMetricsDTO::class, $result);
    }

    #[Test]
    public function it_handles_article_viewed_metric(): void
    {
        $metricType = FinancerMetricType::ARTICLE_VIEWED;
        $period = MetricPeriod::THIRTY_DAYS;
        $expectedData = [
            'total' => 250,
            'daily' => [
                ['date' => '2025-07-01', 'count' => 10],
                ['date' => '2025-07-02', 'count' => 20],
            ],
        ];

        $this->factoryMock
            ->shouldReceive('make')
            ->once()
            ->with($metricType)
            ->andReturn($this->calculatorMock);

        $this->calculatorMock
            ->shouldReceive('getCacheKey')
            ->once()
            ->andReturn('test-key');

        $this->calculatorMock
            ->shouldReceive('getCacheTTL')
            ->once()
            ->andReturn(3600);

        $this->calculatorMock
            ->shouldReceive('calculate')
            ->once()
            ->andReturn($expectedData);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $result = $this->service->getMetric(
            $this->financer->id,
            $metricType,
            ['period' => $period]
        );

        $this->assertInstanceOf(IMetricDTO::class, $result);
    }

    #[Test]
    public function it_throws_exception_for_invalid_metric_type(): void
    {
        $this->expectException(InvalidMetricTypeException::class);

        $this->service->getMetric(
            $this->financer->id,
            'invalid-metric',
            ['period' => MetricPeriod::SEVEN_DAYS]
        );
    }

    #[Test]
    public function it_handles_voucher_purchases_metric(): void
    {
        $metricType = FinancerMetricType::VOUCHER_PURCHASES;
        $period = MetricPeriod::THREE_MONTHS;
        $expectedData = [
            'total' => 45,
            'daily' => [
                ['date' => '2025-05-01', 'count' => 5],
                ['date' => '2025-05-02', 'count' => 8],
            ],
            'total_volume' => 4500,
        ];

        $this->factoryMock
            ->shouldReceive('make')
            ->once()
            ->with($metricType)
            ->andReturn($this->calculatorMock);

        $this->calculatorMock
            ->shouldReceive('getCacheKey')
            ->once()
            ->andReturn('voucher-cache-key');

        $this->calculatorMock
            ->shouldReceive('getCacheTTL')
            ->once()
            ->andReturn(7200);

        $this->calculatorMock
            ->shouldReceive('calculate')
            ->once()
            ->andReturn($expectedData);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $result = $this->service->getMetric(
            $this->financer->id,
            $metricType,
            ['period' => $period]
        );

        $this->assertInstanceOf(IMetricDTO::class, $result);
    }

    #[Test]
    public function it_handles_module_usage_metric(): void
    {
        $metricType = FinancerMetricType::MODULE_USAGE;
        $period = MetricPeriod::THIRTY_DAYS;
        $expectedData = [
            'total' => 500,
            'modules' => [
                ['name' => 'Vouchers', 'total_uses' => 200],
                ['name' => 'Benefits', 'total_uses' => 300],
            ],
        ];

        $this->factoryMock
            ->shouldReceive('make')
            ->once()
            ->with($metricType)
            ->andReturn($this->calculatorMock);

        $this->calculatorMock
            ->shouldReceive('getCacheKey')
            ->once()
            ->andReturn('module-usage-cache-key');

        $this->calculatorMock
            ->shouldReceive('getCacheTTL')
            ->once()
            ->andReturn(3600);

        $this->calculatorMock
            ->shouldReceive('calculate')
            ->once()
            ->andReturn($expectedData);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $result = $this->service->getMetric(
            $this->financer->id,
            $metricType,
            ['period' => $period]
        );

        $this->assertInstanceOf(IMetricDTO::class, $result);
    }

    #[Test]
    public function it_handles_shortcuts_clicks_metric_with_new_format(): void
    {
        $metricType = FinancerMetricType::SHORTCUTS_CLICKS;
        $period = MetricPeriod::SEVEN_DAYS;
        $expectedData = [
            'total' => 150,
            'daily' => [
                ['date' => '2025-07-01', 'count' => 50],
                ['date' => '2025-07-02', 'count' => 100],
            ],
            'shortcuts' => [
                'Benefits' => ['daily' => [['count' => 30], ['count' => 60]]],
                'Vouchers' => ['daily' => [['count' => 20], ['count' => 40]]],
            ],
        ];

        $this->factoryMock
            ->shouldReceive('make')
            ->once()
            ->with($metricType)
            ->andReturn($this->calculatorMock);

        $this->calculatorMock
            ->shouldReceive('getCacheKey')
            ->once()
            ->andReturn('shortcuts-cache-key');

        $this->calculatorMock
            ->shouldReceive('getCacheTTL')
            ->once()
            ->andReturn(3600);

        $this->calculatorMock
            ->shouldReceive('calculate')
            ->once()
            ->andReturn($expectedData);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $result = $this->service->getMetric(
            $this->financer->id,
            $metricType,
            ['period' => $period]
        );

        $this->assertInstanceOf(IMetricDTO::class, $result);
    }

    #[Test]
    public function it_handles_bounce_rate_metric(): void
    {
        $metricType = FinancerMetricType::BOUNCE_RATE;
        $period = MetricPeriod::THIRTY_DAYS;
        $expectedData = ['bounce_rate' => 25.5];

        $this->factoryMock
            ->shouldReceive('make')
            ->once()
            ->with($metricType)
            ->andReturn($this->calculatorMock);

        $this->calculatorMock
            ->shouldReceive('getCacheKey')
            ->once()
            ->andReturn('bounce-rate-cache-key');

        $this->calculatorMock
            ->shouldReceive('getCacheTTL')
            ->once()
            ->andReturn(3600);

        $this->calculatorMock
            ->shouldReceive('calculate')
            ->once()
            ->andReturn($expectedData);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $result = $this->service->getMetric(
            $this->financer->id,
            $metricType,
            ['period' => $period]
        );

        $this->assertInstanceOf(IMetricDTO::class, $result);
    }

    #[Test]
    public function it_validates_period_in_parameters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->getMetric(
            $this->financer->id,
            FinancerMetricType::ACTIVE_BENEFICIARIES,
            ['period' => 'invalid-period']
        );
    }

    #[Test]
    public function it_uses_default_period_when_not_provided(): void
    {
        $metricType = FinancerMetricType::ACTIVE_BENEFICIARIES;
        $expectedData = ['total' => 100, 'daily' => []];

        $this->factoryMock
            ->shouldReceive('make')
            ->once()
            ->with($metricType)
            ->andReturn($this->calculatorMock);

        $this->calculatorMock
            ->shouldReceive('getCacheKey')
            ->once()
            ->andReturn('test-cache-key');

        $this->calculatorMock
            ->shouldReceive('getCacheTTL')
            ->once()
            ->andReturn(3600);

        $this->calculatorMock
            ->shouldReceive('calculate')
            ->once()
            ->andReturn($expectedData);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        // No period provided in parameters
        $result = $this->service->getMetric(
            $this->financer->id,
            $metricType,
            []
        );

        $this->assertInstanceOf(IMetricDTO::class, $result);
    }
}
