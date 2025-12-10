<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects\Metrics;

use App\ValueObjects\Metrics\DateRange;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('metrics')]
class DateRangeTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset any mocked time to avoid leaking into other tests
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function it_creates_date_range_with_valid_dates(): void
    {
        $start = Carbon::now()->subDays(7);
        $end = Carbon::now();

        $dateRange = new DateRange($start, $end);

        $this->assertEquals($start, $dateRange->start);
        $this->assertEquals($end, $dateRange->end);
    }

    #[Test]
    public function it_throws_exception_when_start_date_is_after_end_date(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Start date must be before end date');

        new DateRange(Carbon::now(), Carbon::now()->subDays(7));
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $start = Carbon::now()->subDays(7);
        $end = Carbon::now();
        $dateRange = new DateRange($start, $end);

        $array = $dateRange->toArray();

        $this->assertCount(2, $array);
        $this->assertEquals($start, $array[0]);
        $this->assertEquals($end, $array[1]);
    }

    #[Test]
    public function it_creates_from_period(): void
    {
        Carbon::setTestNow('2024-01-15 12:00:00');

        $dateRange = DateRange::fromPeriod('7_days');

        $this->assertEquals('2024-01-08', $dateRange->start->format('Y-m-d'));
        $this->assertEquals('2024-01-15', $dateRange->end->format('Y-m-d'));
    }

    #[Test]
    public function it_calculates_duration_in_days(): void
    {
        $dateRange = new DateRange(
            Carbon::parse('2024-01-01'),
            Carbon::parse('2024-01-08')
        );

        $this->assertEquals(7, $dateRange->getDurationInDays());
    }

    #[Test]
    public function it_checks_if_date_is_contained(): void
    {
        $dateRange = new DateRange(
            Carbon::parse('2024-01-01'),
            Carbon::parse('2024-01-31')
        );

        $this->assertTrue($dateRange->contains(Carbon::parse('2024-01-15')));
        $this->assertFalse($dateRange->contains(Carbon::parse('2024-02-01')));
    }
}
