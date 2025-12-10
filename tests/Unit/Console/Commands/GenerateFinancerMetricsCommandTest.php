<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\Metrics\GenerateFinancerMetricsCommand;
use App\Jobs\ProcessFinancerMetricsJob;
use App\Models\Financer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('metrics')]
#[Group('financer')]
class GenerateFinancerMetricsCommandTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_exists_as_a_command(): void
    {
        $this->assertTrue(
            class_exists(GenerateFinancerMetricsCommand::class),
            'GenerateFinancerMetricsCommand class should exist'
        );
    }

    #[Test]
    public function it_is_registered_in_artisan(): void
    {
        $this->artisan('list')
            ->expectsOutputToContain('metrics:generate-financer')
            ->assertSuccessful();
    }

    #[Test]
    public function it_generates_metrics_for_specific_financer(): void
    {
        Queue::fake();
        $financer = ModelFactory::createFinancer();

        $this->artisan('metrics:generate-financer', [
            '--financer' => $financer->id,
        ])
            ->expectsOutput("Queuing metrics generation for financer: {$financer->id}")
            ->expectsOutputToContain('Period:')
            ->expectsOutput('Metrics generation job queued successfully.')
            ->assertSuccessful();

        Queue::assertPushed(ProcessFinancerMetricsJob::class, function ($job) use ($financer): bool {
            return $job->financerId === $financer->id
                && $job->period === '30d'; // Default period
        });
    }

    #[Test]
    public function it_generates_metrics_for_specific_date_range(): void
    {
        Queue::fake();
        $financer = ModelFactory::createFinancer();
        $dateFrom = '2025-01-15';
        $dateTo = '2025-01-15';

        $this->artisan('metrics:generate-financer', [
            '--financer' => $financer->id,
            '--date-from' => $dateFrom,
            '--date-to' => $dateTo,
            '--period' => 'custom',
        ])
            ->expectsOutput("Queuing metrics generation for financer: {$financer->id}")
            ->expectsOutput("Period: custom ({$dateFrom} to {$dateTo})")
            ->expectsOutput('Metrics generation job queued successfully.')
            ->assertSuccessful();

        Queue::assertPushed(ProcessFinancerMetricsJob::class, function ($job) use ($financer, $dateFrom, $dateTo): bool {
            return $job->financerId === $financer->id
                && $job->dateFrom->toDateString() === $dateFrom
                && $job->dateTo->toDateString() === $dateTo;
        });
    }

    #[Test]
    public function it_generates_metrics_for_all_active_financers(): void
    {
        Queue::fake();

        // Create multiple financers
        $activeFinancers = collect();
        for ($i = 0; $i < 3; $i++) {
            $activeFinancers->push(ModelFactory::createFinancer(['status' => Financer::STATUS_ACTIVE]));
        }
        $inactiveFinancer = ModelFactory::createFinancer(['status' => Financer::STATUS_ARCHIVED]);

        $this->artisan('metrics:generate-financer', [
            '--all' => true,
        ])
            ->expectsOutput('Queuing metrics generation for all active financers...')
            ->expectsOutputToContain('Queued')
            ->assertSuccessful();

        // Verify jobs queued for active financers only
        foreach ($activeFinancers as $financer) {
            Queue::assertPushed(ProcessFinancerMetricsJob::class, function ($job) use ($financer): bool {
                return $job->financerId === $financer->id;
            });
        }

        // Verify no job queued for inactive financer
        Queue::assertNotPushed(ProcessFinancerMetricsJob::class, function ($job) use ($inactiveFinancer): bool {
            return $job->financerId === $inactiveFinancer->id;
        });
    }

    #[Test]
    public function it_validates_financer_exists(): void
    {
        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000'; // Valid UUID that doesn't exist

        $this->artisan('metrics:generate-financer', [
            '--financer' => $nonExistentId,
        ])
            ->expectsOutput("Financer not found: {$nonExistentId}")
            ->assertFailed();
    }

    #[Test]
    public function it_validates_date_format(): void
    {
        $financer = ModelFactory::createFinancer();

        $this->artisan('metrics:generate-financer', [
            '--financer' => $financer->id,
            '--date-from' => 'invalid-date',
            '--date-to' => 'invalid-date',
            '--period' => 'custom',
        ])
            ->expectsOutput('Invalid date format. Please use YYYY-MM-DD.')
            ->assertFailed();
    }

    #[Test]
    public function it_requires_either_financer_or_all_option(): void
    {
        $this->artisan('metrics:generate-financer')
            ->expectsOutput('Please specify either --financer=ID or --all option.')
            ->assertFailed();
    }

    #[Test]
    public function it_cannot_use_both_financer_and_all_options(): void
    {
        $financer = Financer::factory()->create();

        $this->artisan('metrics:generate-financer', [
            '--financer' => $financer->id,
            '--all' => true,
        ])
            ->expectsOutput('Cannot use both --financer and --all options together.')
            ->assertFailed();
    }

    #[Test]
    public function it_handles_date_range_option(): void
    {
        Queue::fake();
        $financer = Financer::factory()->create();
        $startDate = '2025-01-01';
        $endDate = '2025-01-03';

        $this->artisan('metrics:generate-financer', [
            '--financer' => $financer->id,
            '--date-from' => $startDate,
            '--date-to' => $endDate,
            '--period' => 'custom',
        ])
            ->expectsOutput("Queuing metrics generation for financer: {$financer->id}")
            ->expectsOutput("Period: custom ({$startDate} to {$endDate})")
            ->expectsOutput('Metrics generation job queued successfully.')
            ->assertSuccessful();

        // Verify job queued for the date range
        Queue::assertPushed(ProcessFinancerMetricsJob::class, function ($job) use ($financer, $startDate, $endDate): bool {
            return $job->financerId === $financer->id
                && $job->dateFrom->toDateString() === $startDate
                && $job->dateTo->toDateString() === $endDate;
        });
    }

    #[Test]
    public function it_validates_date_range_order(): void
    {
        $financer = Financer::factory()->create();

        $this->artisan('metrics:generate-financer', [
            '--financer' => $financer->id,
            '--date-from' => '2025-01-10',
            '--date-to' => '2025-01-05',
            '--period' => 'custom',
        ])
            ->expectsOutput('Date from must be before or equal to date to.')
            ->assertFailed();
    }

    #[Test]
    public function it_shows_progress_bar_for_multiple_financers(): void
    {
        Queue::fake();

        Financer::factory(5)->create(['status' => Financer::STATUS_ACTIVE]);

        $this->artisan('metrics:generate-financer', [
            '--all' => true,
            '--verbose' => true,
        ])
            ->expectsOutput('Queuing metrics generation for all active financers...')
            ->assertSuccessful();
    }

    #[Test]
    public function it_supports_dry_run_mode(): void
    {
        Queue::fake();
        $financer = Financer::factory()->create();

        $this->artisan('metrics:generate-financer', [
            '--financer' => $financer->id,
            '--dry-run' => true,
        ])
            ->expectsOutput('[DRY RUN] Would queue metrics generation for financer: '.$financer->id)
            ->expectsOutputToContain('[DRY RUN] Period:')
            ->assertSuccessful();

        // Verify no job was actually queued
        Queue::assertNothingPushed();
    }
}
