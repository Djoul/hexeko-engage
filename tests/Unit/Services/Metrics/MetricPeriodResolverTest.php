<?php

namespace Tests\Unit\Services\Metrics;

use App\Enums\MetricPeriod;
use App\Exceptions\InvalidPeriodException;
use App\Services\Metrics\MetricPeriodResolver;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('metrics')]
class MetricPeriodResolverTest extends TestCase
{
    private MetricPeriodResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new MetricPeriodResolver;
        Carbon::setTestNow('2025-07-31 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function it_resolves_seven_days_period(): void
    {
        $result = $this->resolver->resolve(MetricPeriod::SEVEN_DAYS);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('from', $result);
        $this->assertArrayHasKey('to', $result);
        $this->assertInstanceOf(Carbon::class, $result['from']);
        $this->assertInstanceOf(Carbon::class, $result['to']);

        // 7 days period should be from 6 days ago to today (7 days total including today)
        $this->assertEquals('2025-07-25', $result['from']->toDateString());
        $this->assertEquals('2025-07-31', $result['to']->toDateString());
    }

    #[Test]
    public function it_resolves_thirty_days_period(): void
    {
        $result = $this->resolver->resolve(MetricPeriod::THIRTY_DAYS);

        // 30 days period should be from 29 days ago to today (30 days total including today)
        $this->assertEquals('2025-07-02', $result['from']->toDateString());
        $this->assertEquals('2025-07-31', $result['to']->toDateString());
    }

    #[Test]
    public function it_resolves_three_months_period(): void
    {
        $result = $this->resolver->resolve(MetricPeriod::THREE_MONTHS);

        // 3 months period: subMonths(3)->addDay() from July 31 = May 2
        $this->assertEquals('2025-05-02', $result['from']->toDateString());
        $this->assertEquals('2025-07-31', $result['to']->toDateString());
    }

    #[Test]
    public function it_resolves_six_months_period(): void
    {
        $result = $this->resolver->resolve(MetricPeriod::SIX_MONTHS);

        $this->assertEquals('2025-01-31', $result['from']->toDateString());
        $this->assertEquals('2025-07-31', $result['to']->toDateString());
    }

    #[Test]
    public function it_resolves_twelve_months_period(): void
    {
        $result = $this->resolver->resolve(MetricPeriod::TWELVE_MONTHS);

        $this->assertEquals('2024-07-31', $result['from']->toDateString());
        $this->assertEquals('2025-07-31', $result['to']->toDateString());
    }

    #[Test]
    public function it_resolves_custom_period_with_valid_dates(): void
    {
        $customFrom = Carbon::parse('2025-06-01');
        $customTo = Carbon::parse('2025-06-30');

        $result = $this->resolver->resolve(MetricPeriod::CUSTOM, $customFrom, $customTo);

        $this->assertEquals('2025-06-01', $result['from']->toDateString());
        $this->assertEquals('2025-06-30', $result['to']->toDateString());
    }

    #[Test]
    public function it_throws_exception_for_custom_period_without_dates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Custom period requires date_from and date_to');

        $this->resolver->resolve(MetricPeriod::CUSTOM);
    }

    #[Test]
    public function it_throws_exception_for_custom_period_with_only_from_date(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Custom period requires date_from and date_to');

        $this->resolver->resolve(MetricPeriod::CUSTOM, Carbon::now());
    }

    #[Test]
    public function it_throws_exception_for_custom_period_with_only_to_date(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Custom period requires date_from and date_to');

        $this->resolver->resolve(MetricPeriod::CUSTOM, null, Carbon::now());
    }

    #[Test]
    public function it_validates_valid_periods(): void
    {
        $validPeriods = [
            MetricPeriod::SEVEN_DAYS,
            MetricPeriod::THIRTY_DAYS,
            MetricPeriod::THREE_MONTHS,
            MetricPeriod::SIX_MONTHS,
            MetricPeriod::TWELVE_MONTHS,
            MetricPeriod::CUSTOM,
        ];

        foreach ($validPeriods as $period) {
            // Should not throw exception
            $this->resolver->validatePeriod($period);
            $this->assertTrue(true); // Assert that no exception was thrown
        }
    }

    #[Test]
    public function it_throws_exception_for_invalid_period(): void
    {
        $this->expectException(InvalidPeriodException::class);
        $this->expectExceptionMessage('Invalid period: invalid_period');

        $this->resolver->validatePeriod('invalid_period');
    }

    #[Test]
    public function it_resolves_period_with_reference_date(): void
    {
        $referenceDate = Carbon::parse('2025-01-15');

        $result = $this->resolver->resolve(
            MetricPeriod::SEVEN_DAYS,
            null,
            null,
            $referenceDate
        );

        // Should calculate 7 days from reference date
        $this->assertEquals('2025-01-09', $result['from']->toDateString());
        $this->assertEquals('2025-01-15', $result['to']->toDateString());
    }

    #[Test]
    public function it_ensures_from_date_is_before_to_date_for_custom_period(): void
    {
        $customFrom = Carbon::parse('2025-06-30');
        $customTo = Carbon::parse('2025-06-01');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('date_from must be before date_to');

        $this->resolver->resolve(MetricPeriod::CUSTOM, $customFrom, $customTo);
    }

    #[Test]
    public function it_returns_cache_ttl_for_each_period(): void
    {
        $ttls = [
            MetricPeriod::SEVEN_DAYS => 3600,      // 1 hour
            MetricPeriod::THIRTY_DAYS => 7200,     // 2 hours
            MetricPeriod::THREE_MONTHS => 14400,   // 4 hours
            MetricPeriod::SIX_MONTHS => 28800,     // 8 hours
            MetricPeriod::TWELVE_MONTHS => 86400,  // 24 hours
            MetricPeriod::CUSTOM => 3600,          // 1 hour
        ];

        foreach ($ttls as $period => $expectedTtl) {
            $ttl = $this->resolver->getCacheTtlForPeriod($period);
            $this->assertEquals($expectedTtl, $ttl);
        }
    }
}
