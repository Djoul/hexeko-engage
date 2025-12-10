<?php

namespace Tests\Unit\Events\Apideck;

use App\Events\Apideck\SIRHSyncStartedEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

#[Group('apideck')]
class SIRHSyncStartedEventTest extends TestCase
{
    #[Test]
    public function it_broadcasts_on_correct_private_channel(): void
    {
        $syncId = Uuid::uuid4()->toString();
        $financerId = Uuid::uuid4()->toString();
        $userId = Uuid::uuid4()->toString();
        $totalEmployees = 150;
        $totalBatches = 3;

        $event = new SIRHSyncStartedEvent(
            $syncId,
            $financerId,
            $userId,
            $totalEmployees,
            $totalBatches
        );

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-user.'.$userId, $channels[0]->name);
    }

    #[Test]
    public function it_broadcasts_with_correct_event_name(): void
    {
        $event = new SIRHSyncStartedEvent(
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            150,
            3
        );

        $this->assertEquals('sirh.sync.started', $event->broadcastAs());
    }

    #[Test]
    public function it_includes_all_required_data_in_broadcast(): void
    {
        $syncId = Uuid::uuid4()->toString();
        $financerId = Uuid::uuid4()->toString();
        $userId = Uuid::uuid4()->toString();
        $totalEmployees = 150;
        $totalBatches = 3;

        $event = new SIRHSyncStartedEvent(
            $syncId,
            $financerId,
            $userId,
            $totalEmployees,
            $totalBatches
        );

        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('sync_id', $broadcastData);
        $this->assertArrayHasKey('financer_id', $broadcastData);
        $this->assertArrayHasKey('total_employees', $broadcastData);
        $this->assertArrayHasKey('total_batches', $broadcastData);
        $this->assertArrayHasKey('started_at', $broadcastData);

        $this->assertEquals($syncId, $broadcastData['sync_id']);
        $this->assertEquals($financerId, $broadcastData['financer_id']);
        $this->assertEquals($totalEmployees, $broadcastData['total_employees']);
        $this->assertEquals($totalBatches, $broadcastData['total_batches']);
    }

    #[Test]
    public function it_implements_should_broadcast_interface(): void
    {
        $event = new SIRHSyncStartedEvent(
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            150,
            3
        );

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
    }
}
