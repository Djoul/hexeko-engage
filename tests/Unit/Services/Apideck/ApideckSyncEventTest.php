<?php

namespace Tests\Unit\Services\Apideck;

use App\Events\ApideckSyncCompleted;
use App\Models\Financer;
use App\Services\Apideck\ApideckService;
use Bus;
use Event;
use Exception;
use Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('apideck')]
class ApideckSyncEventTest extends ProtectedRouteTestCase
{
    protected Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache to prevent interference
        cache()->flush();

        $this->financer = Financer::factory()->create([
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        config([
            'services.apideck.base_url' => 'https://unify.apideck.com',
            'services.apideck.key' => 'test-api-key',
            'services.apideck.app_id' => 'test-app-id',
            'services.apideck.service_id' => 'bamboohr',
        ]);
    }

    #[Test]
    public function it_dispatches_sync_completed_event_on_successful_sync(): void
    {
        // Track dispatched events manually
        $dispatchedEvents = [];
        Event::listen(ApideckSyncCompleted::class, function ($event) use (&$dispatchedEvents): void {
            $dispatchedEvents[] = $event;
        });

        // Fake mail to prevent email sending issues
        Mail::fake();
        // Use sync queue instead of Bus::fake() to allow jobs to execute
        config(['queue.default' => 'sync']);
        // Disable broadcasting to prevent queue conflicts with ShouldBroadcast
        config(['broadcasting.default' => 'null']);

        // Use unique emails to avoid conflicts with existing test data
        $uniqueId = uniqid();
        $employees = [
            [
                'id' => 'emp-001-'.$uniqueId,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'emails' => [['email' => 'john.doe.'.$uniqueId.'@example.com', 'type' => 'primary']],
            ],
            [
                'id' => 'emp-002-'.$uniqueId,
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'emails' => [['email' => 'jane.smith.'.$uniqueId.'@example.com', 'type' => 'primary']],
            ],
        ];

        Http::fake([
            // Mock calculateTotalEmployees
            'https://unify.apideck.com/hris/employees?limit=200' => Http::response([
                'data' => $employees,
                'meta' => [
                    'items_on_page' => 2,
                    'cursors' => ['next' => null],
                ],
            ], 200),
            // Mock fetchEmployees
            'https://unify.apideck.com/hris/employees*' => Http::response([
                'data' => $employees,
                'meta' => [
                    'items_on_page' => 2,
                    'cursors' => ['next' => null],
                ],
            ], 200),
        ]);

        $service = new ApideckService;
        $service->initializeConsumerId($this->financer->id);

        $service->syncAll(['financer_id' => $this->financer->id]);

        // Assert event was dispatched
        $this->assertCount(1, $dispatchedEvents, 'ApideckSyncCompleted event should be dispatched once');
        $event = $dispatchedEvents[0];
        $this->assertEquals($this->financer->id, $event->financerId);
        $this->assertEquals(2, $event->syncData['created']);
        $this->assertEquals(0, $event->syncData['updated']);
        $this->assertEquals(0, $event->syncData['failed']);
        $this->assertEquals(2, $event->syncData['total']);
        $this->assertArrayHasKey('duration_seconds', $event->syncData);
        $this->assertArrayHasKey('started_at', $event->syncData);
        $this->assertArrayHasKey('completed_at', $event->syncData);
        $this->assertEquals('sync.success', $event->type);
        $this->assertEquals('success', $event->severity);
    }

    #[Test]
    public function it_dispatches_sync_error_event_when_sync_fails(): void
    {
        // Clear cache to ensure clean state
        cache()->flush();

        Event::fake([ApideckSyncCompleted::class]);

        Http::fake([
            '*' => Http::response([
                'error' => 'API rate limit exceeded',
            ], 429),
        ]);

        $service = new ApideckService;
        $service->initializeConsumerId($this->financer->id);

        $result = $service->syncAll(['financer_id' => $this->financer->id]);

        Event::assertDispatched(ApideckSyncCompleted::class, function ($event): bool {
            return $event->financerId === $this->financer->id
                && $event->type === 'sync.error'
                && $event->severity === 'error';
        });

        $this->assertEquals('Apideck API Error: {"error":"API rate limit exceeded"}', $result['error'] ?? null);
    }

    #[Test]
    public function it_dispatches_partial_sync_event_when_some_employees_fail(): void
    {
        Event::fake([ApideckSyncCompleted::class]);
        // Use sync queue instead of Bus::fake() to allow jobs to execute
        config(['queue.default' => 'sync']);

        $employees = [
            [
                'id' => 'emp-001',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'emails' => [['email' => 'john.doe@example.com', 'type' => 'primary']],
            ],
            [
                'id' => 'emp-002',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'emails' => [['email' => 'invalid-email', 'type' => 'primary']], // This will cause validation to fail
            ],
            [
                'id' => 'emp-003',
                'first_name' => 'Bob',
                'last_name' => 'Johnson',
                'emails' => [['email' => 'bob.johnson@example.com', 'type' => 'primary']],
            ],
        ];

        Http::fake([
            // Mock all requests to employees endpoint
            '*' => Http::response([
                'data' => $employees,
                'meta' => [
                    'items_on_page' => 3,
                    'cursors' => ['next' => null],
                ],
            ], 200),
        ]);

        $service = new ApideckService;
        $service->initializeConsumerId($this->financer->id);

        try {
            $result = $service->syncAll(['financer_id' => $this->financer->id]);
        } catch (Exception $e) {
            $this->fail('syncAll threw exception: '.$e->getMessage().' - '.$e->getTraceAsString());
        }

        Event::assertDispatched(ApideckSyncCompleted::class);

        Event::assertDispatched(ApideckSyncCompleted::class, function ($event): bool {
            // The sync should complete with at least the total count
            // Note: Since Bus::fake() is used, jobs won't execute
            return $event->financerId === $this->financer->id
                && $event->syncData['total'] === 3
                && isset($event->syncData['created'])
                && isset($event->syncData['failed']);
        });
    }

    #[Test]
    public function it_includes_division_context_in_sync_event(): void
    {
        Event::fake([ApideckSyncCompleted::class]);
        // Use sync queue instead of Bus::fake() to allow jobs to execute
        config(['queue.default' => 'sync']);

        $divisionId = 'division-123';
        $employees = [
            [
                'id' => 'emp-001',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'emails' => [['email' => 'john.doe@example.com', 'type' => 'primary']],
            ],
        ];

        Http::fake([
            'https://unify.apideck.com/hris/employees*' => Http::response([
                'data' => $employees,
                'meta' => [
                    'items_on_page' => 1,
                    'cursors' => ['next' => null],
                ],
            ], 200),
        ]);

        $service = new ApideckService;
        $service->initializeConsumerId($this->financer->id);

        $service->syncAll([
            'financer_id' => $this->financer->id,
            'division_id' => $divisionId,
        ]);

        Event::assertDispatched(ApideckSyncCompleted::class, function ($event) use ($divisionId): bool {
            return $event->financerId === $this->financer->id
                && $event->syncData['division_id'] === $divisionId;
        });
    }

    #[Test]
    public function it_broadcasts_correct_type_and_severity_for_no_changes(): void
    {
        Event::fake([ApideckSyncCompleted::class]);
        // Use sync queue instead of Bus::fake() to allow jobs to execute
        config(['queue.default' => 'sync']);

        Http::fake([
            'https://unify.apideck.com/hris/employees*' => Http::response([
                'data' => [],
                'meta' => [
                    'items_on_page' => 0,
                    'cursors' => ['next' => null],
                ],
            ], 200),
        ]);

        $service = new ApideckService;
        $service->initializeConsumerId($this->financer->id);

        $service->syncAll(['financer_id' => $this->financer->id]);

        Event::assertDispatched(ApideckSyncCompleted::class, function ($event): bool {
            $broadcastData = $event->broadcastWith();

            return ! isset($broadcastData['toast'])
                && $broadcastData['type'] === 'sync.no_changes'
                && $broadcastData['severity'] === 'info';
        });
    }
}
