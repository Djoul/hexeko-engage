<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Console\Kernel;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionObject;
use Tests\TestCase;

#[Group('console')]
#[Group('scheduling')]
#[Group('metrics')]
class KernelSchedulingTest extends TestCase
{
    #[Test]
    public function it_schedules_financer_metrics_generation(): void
    {
        // Ensure the command is registered
        $this->artisan('list')
            ->assertSuccessful()
            ->expectsOutputToContain('metrics:generate-financer');

        // Force the kernel to register commands and schedules
        $kernel = $this->app->make(Kernel::class);

        // Use reflection to access the schedule method directly
        $reflection = new ReflectionObject($kernel);
        $method = $reflection->getMethod('schedule');
        $method->setAccessible(true);

        $schedule = $this->app->make(Schedule::class);
        $method->invoke($kernel, $schedule);

        // Get all scheduled events
        $allEvents = collect($schedule->events());

        // Find the metrics command
        $events = $allEvents->filter(function (Event $event): bool {
            // Check the actual command by building it
            $builtCommand = $event->buildCommand();

            return str_contains($builtCommand, 'metrics:generate-financer');
        });

        $this->assertCount(1, $events, 'Financer metrics command should be scheduled');

        $event = $events->first();

        // Verify schedule timing
        $this->assertEquals('0 2 * * *', $event->expression); // Daily at 2 AM

        // Verify it runs on one server only
        $this->assertTrue($event->onOneServer);

        // Verify it runs in background
        $this->assertTrue($event->runInBackground);

        // Verify output logging
        $this->assertStringContainsString('financer-metrics.log', $event->output);
    }

    #[Test]
    public function it_runs_at_correct_time(): void
    {
        // Force the kernel to register commands and schedules
        $kernel = $this->app->make(Kernel::class);

        // Use reflection to access the schedule method directly
        $reflection = new ReflectionObject($kernel);
        $method = $reflection->getMethod('schedule');
        $method->setAccessible(true);

        $schedule = $this->app->make(Schedule::class);
        $method->invoke($kernel, $schedule);

        $event = collect($schedule->events())->first(function (Event $event): bool {
            $builtCommand = $event->buildCommand();

            return str_contains($builtCommand, 'metrics:generate-financer');
        });

        $this->assertNotNull($event, 'Financer metrics command should be found in schedule');

        // Test that it runs at 2:00 AM
        Carbon::setTestNow('2025-01-15 02:00:00');
        $this->assertTrue($event->isDue($this->app));

        // Test that it doesn't run at other times
        Carbon::setTestNow('2025-01-15 01:59:59');
        $this->assertFalse($event->isDue($this->app));

        // Laravel scheduler has a minute-based tolerance, so 02:01:00 should not be due
        Carbon::setTestNow('2025-01-15 02:01:00');
        $this->assertFalse($event->isDue($this->app));

        Carbon::setTestNow('2025-01-15 14:00:00');
        $this->assertFalse($event->isDue($this->app));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset time
        parent::tearDown();
    }
}
