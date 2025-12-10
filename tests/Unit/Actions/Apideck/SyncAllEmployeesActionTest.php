<?php

namespace Tests\Unit\Actions\Apideck;

use App\Actions\Apideck\SyncAllEmployeesAction;
use App\Services\Apideck\ApideckService;
use Event;
use Exception;
use Log;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('apideck')]
class SyncAllEmployeesActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_can_handle_sync_all_employees(): void
    {
        // Mock the ApideckService
        $mockService = Mockery::mock(ApideckService::class);
        $mockService->shouldReceive('initializeConsumerId')
            ->once()
            ->with('test-financer');
        $mockService->shouldReceive('fetchAllEmployees')
            ->once()
            ->andReturn([
                'employees' => [],
                'meta' => ['total_items' => 0],
            ]);

        $this->app->instance(ApideckService::class, $mockService);

        // Mock Log facade and Event
        Log::shouldReceive('info')->atLeast()->once();
        Event::fake();

        // Create and handle the action
        $action = new SyncAllEmployeesAction(['financer_id' => 'test-financer']);

        // This should not throw any exceptions
        $action->handle($mockService);

        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    #[Test]
    public function it_logs_errors_when_sync_fails(): void
    {
        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('error')->atLeast()->once();

        // Mock the ApideckService to throw an exception
        $mockService = Mockery::mock(ApideckService::class);
        $mockService->shouldReceive('initializeConsumerId')
            ->once()
            ->with('test-financer');
        $mockService->shouldReceive('fetchAllEmployees')
            ->once()
            ->andThrow(new Exception('Sync failed'));

        $this->app->instance(ApideckService::class, $mockService);

        // Create and handle the action
        $action = new SyncAllEmployeesAction(['financer_id' => 'test-financer']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Sync failed');

        $action->handle($mockService);
    }
}
