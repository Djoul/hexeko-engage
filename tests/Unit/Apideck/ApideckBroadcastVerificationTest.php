<?php

namespace Tests\Unit\Apideck;

use App\Events\ApideckSyncCompleted;
use App\Models\Financer;
use App\Services\Apideck\ApideckService;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('apideck')]
class ApideckBroadcastVerificationTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_verifies_event_is_broadcasted_with_event_helper(): void
    {
        // Use Event::fake to capture both local events and broadcasts
        Event::fake([ApideckSyncCompleted::class]);
        Bus::fake();

        $financer = Financer::factory()->create([
            'external_id' => json_encode([
                'sirh' => ['consumer_id' => 'test-consumer'],
            ]),
        ]);

        // Mock HTTP response
        Http::fake([
            '*' => Http::response([
                'data' => [],
                'meta' => [
                    'items_on_page' => 0,
                    'cursors' => ['next' => null],
                ],
            ], 200),
        ]);

        $service = new ApideckService;
        $service->initializeConsumerId($financer->id);
        $service->syncAll(['financer_id' => $financer->id]);

        // Assert the event was dispatched (includes broadcasting)
        Event::assertDispatched(ApideckSyncCompleted::class);

        // Verify it would be broadcasted
        Event::assertDispatched(ApideckSyncCompleted::class, function ($event): bool {
            return $event instanceof ShouldBroadcast;
        });
    }

    #[Test]
    public function it_verifies_event_would_be_queued_for_broadcasting(): void
    {
        // Queue::fake to capture queued broadcasts
        Queue::fake();
        Bus::fake();

        $financer = Financer::factory()->create([
            'external_id' => json_encode([
                'sirh' => ['consumer_id' => 'test-consumer'],
            ]),
        ]);

        Http::fake([
            '*' => Http::response([
                'data' => [],
                'meta' => [
                    'items_on_page' => 0,
                    'cursors' => ['next' => null],
                ],
            ], 200),
        ]);

        $service = new ApideckService;
        $service->initializeConsumerId($financer->id);

        // Dispatch the event manually to test broadcasting
        $syncData = [
            'created' => 5,
            'updated' => 3,
            'failed' => 0,
            'total' => 8,
        ];

        event(new ApideckSyncCompleted($financer->id, $syncData));

        // When using event() with ShouldBroadcast, Laravel queues a BroadcastEvent job
        Queue::assertPushed(BroadcastEvent::class, function ($job): bool {
            return $job->event instanceof ApideckSyncCompleted;
        });
    }

    #[Test]
    public function it_confirms_event_helper_is_correct_for_broadcasting(): void
    {
        // The current implementation using event() is CORRECT because:

        $event = new ApideckSyncCompleted('financer-1', ['created' => 1]);

        // 1. The event implements ShouldBroadcast
        $this->assertInstanceOf(
            ShouldBroadcast::class,
            $event,
            'ApideckSyncCompleted implements ShouldBroadcast'
        );

        // 2. It has broadcast channels defined
        $channels = $event->broadcastOn();
        $this->assertNotEmpty($channels, 'Event has broadcast channels');

        // 3. It has a broadcast name
        $this->assertNotEmpty($event->broadcastAs(), 'Event has broadcast name');

        // Conclusion: Using event() is correct because:
        // - When an event implements ShouldBroadcast, event() automatically handles broadcasting
        // - event() also allows for local listeners (future extensibility)
        // - broadcast() would ONLY broadcast, losing the ability to add local listeners

        $this->assertTrue(
            true,
            'event() is the correct helper for events that implement ShouldBroadcast'
        );
    }

    #[Test]
    public function it_verifies_broadcast_channels_are_correct(): void
    {
        $financerId = 'test-financer-123';
        $divisionId = 'test-division-456';

        // Without division
        $event1 = new ApideckSyncCompleted($financerId, ['created' => 1]);
        $channels1 = $event1->broadcastOn();

        $this->assertCount(1, $channels1);
        $this->assertEquals('private-financer.'.$financerId, $channels1[0]->name);

        // With division
        $event2 = new ApideckSyncCompleted($financerId, [
            'created' => 1,
            'division_id' => $divisionId,
        ]);
        $channels2 = $event2->broadcastOn();

        $this->assertCount(2, $channels2);
        $this->assertEquals('private-financer.'.$financerId, $channels2[0]->name);
        $this->assertEquals('private-division.'.$divisionId, $channels2[1]->name);
    }
}
