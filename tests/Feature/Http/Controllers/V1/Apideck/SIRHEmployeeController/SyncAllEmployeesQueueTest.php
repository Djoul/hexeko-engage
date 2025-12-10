<?php

namespace Tests\Feature\Http\Controllers\V1\Apideck\SIRHEmployeeController;

use App\Actions\Apideck\SyncAllEmployeesAction;
use App\Models\Financer;
use App\Services\Apideck\ApideckService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('apideck')]
class SyncAllEmployeesQueueTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_dispatches_sync_action_to_queue_when_called_from_controller(): void
    {
        Queue::fake();

        // Create authenticated user
        $this->auth = $this->createAuthUser();

        // Create a valid financer
        $financer = Financer::factory()->create();

        // Make the sync request
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/SIRH/employees/sync', [
                'financer_id' => $financer->id,
            ]);

        $response->assertAccepted();

        // Assert the job was pushed to the queue
        Queue::assertPushed(SyncAllEmployeesAction::class, function ($job) use ($financer): bool {
            return $job->params['financer_id'] === $financer->id;
        });
    }

    #[Test]
    public function it_verifies_action_implements_should_queue(): void
    {
        $action = new SyncAllEmployeesAction(['financer_id' => 'test']);

        $this->assertInstanceOf(
            ShouldQueue::class,
            $action,
            'SyncAllEmployeesAction must implement ShouldQueue interface'
        );
    }

    #[Test]
    public function it_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        // Dispatch the action
        SyncAllEmployeesAction::dispatch(['financer_id' => 'test-123']);

        // Verify it was pushed to queue
        Queue::assertPushed(SyncAllEmployeesAction::class);
        Queue::assertPushed(SyncAllEmployeesAction::class, function ($job): bool {
            return $job->params['financer_id'] === 'test-123';
        });
    }

    #[Test]
    public function it_uses_correct_queue_traits(): void
    {
        $action = new SyncAllEmployeesAction([]);

        // Verify it has the necessary queue traits
        $traits = class_uses($action);

        $this->assertContains(
            'Illuminate\Bus\Queueable',
            $traits,
            'Action must use Queueable trait'
        );

        $this->assertContains(
            'Illuminate\Foundation\Bus\Dispatchable',
            $traits,
            'Action must use Dispatchable trait'
        );

        $this->assertContains(
            'Illuminate\Queue\InteractsWithQueue',
            $traits,
            'Action must use InteractsWithQueue trait'
        );
    }

    #[Test]
    public function it_does_not_execute_immediately_when_dispatched(): void
    {
        Queue::fake();

        // Create a mock that should NOT be called
        $this->mock(ApideckService::class)
            ->shouldNotReceive('syncAll');

        // Dispatch the action
        SyncAllEmployeesAction::dispatch(['financer_id' => 'test']);

        // If we get here without the mock being called, the job was queued
        $this->assertTrue(true);

        Queue::assertPushed(SyncAllEmployeesAction::class);
    }
}
