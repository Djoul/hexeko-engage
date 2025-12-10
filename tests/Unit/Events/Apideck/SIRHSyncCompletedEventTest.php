<?php

namespace Tests\Unit\Events\Apideck;

use App\Events\Apideck\SIRHSyncCompletedEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

#[Group('apideck')]
class SIRHSyncCompletedEventTest extends TestCase
{
    #[Test]
    public function it_broadcasts_on_correct_private_channel(): void
    {
        $syncId = Uuid::uuid4()->toString();
        $financerId = Uuid::uuid4()->toString();
        $userId = Uuid::uuid4()->toString();
        $totalEmployees = 150;
        $processedEmployees = 145;
        $failedEmployees = 5;
        $status = 'completed_with_errors';

        $event = new SIRHSyncCompletedEvent(
            $syncId,
            $financerId,
            $userId,
            $totalEmployees,
            $processedEmployees,
            $failedEmployees,
            $status
        );

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-user.'.$userId, $channels[0]->name);
    }

    #[Test]
    public function it_broadcasts_with_correct_event_name(): void
    {
        $event = new SIRHSyncCompletedEvent(
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            150,
            150,
            0,
            'completed'
        );

        $this->assertEquals('sirh.sync.completed', $event->broadcastAs());
    }

    #[Test]
    public function it_includes_all_required_data_in_broadcast(): void
    {
        $syncId = Uuid::uuid4()->toString();
        $financerId = Uuid::uuid4()->toString();
        $userId = Uuid::uuid4()->toString();
        $totalEmployees = 150;
        $processedEmployees = 145;
        $failedEmployees = 5;
        $status = 'completed_with_errors';

        $event = new SIRHSyncCompletedEvent(
            $syncId,
            $financerId,
            $userId,
            $totalEmployees,
            $processedEmployees,
            $failedEmployees,
            $status
        );

        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('sync_id', $broadcastData);
        $this->assertArrayHasKey('financer_id', $broadcastData);
        $this->assertArrayHasKey('total_employees', $broadcastData);
        $this->assertArrayHasKey('processed_employees', $broadcastData);
        $this->assertArrayHasKey('failed_employees', $broadcastData);
        $this->assertArrayHasKey('status', $broadcastData);
        $this->assertArrayHasKey('completed_at', $broadcastData);

        $this->assertEquals($syncId, $broadcastData['sync_id']);
        $this->assertEquals($financerId, $broadcastData['financer_id']);
        $this->assertEquals($totalEmployees, $broadcastData['total_employees']);
        $this->assertEquals($processedEmployees, $broadcastData['processed_employees']);
        $this->assertEquals($failedEmployees, $broadcastData['failed_employees']);
        $this->assertEquals($status, $broadcastData['status']);
    }

    #[Test]
    public function it_handles_successful_completion_status(): void
    {
        $event = new SIRHSyncCompletedEvent(
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            150,
            150,
            0,
            'completed'
        );

        $broadcastData = $event->broadcastWith();

        $this->assertEquals('completed', $broadcastData['status']);
        $this->assertEquals(0, $broadcastData['failed_employees']);
        $this->assertEquals(150, $broadcastData['processed_employees']);
    }

    #[Test]
    public function it_handles_partial_failure_status(): void
    {
        $event = new SIRHSyncCompletedEvent(
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            150,
            100,
            50,
            'completed_with_errors'
        );

        $broadcastData = $event->broadcastWith();

        $this->assertEquals('completed_with_errors', $broadcastData['status']);
        $this->assertEquals(50, $broadcastData['failed_employees']);
        $this->assertEquals(100, $broadcastData['processed_employees']);
    }

    #[Test]
    public function it_handles_complete_failure_status(): void
    {
        $event = new SIRHSyncCompletedEvent(
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            150,
            0,
            150,
            'failed'
        );

        $broadcastData = $event->broadcastWith();

        $this->assertEquals('failed', $broadcastData['status']);
        $this->assertEquals(150, $broadcastData['failed_employees']);
        $this->assertEquals(0, $broadcastData['processed_employees']);
    }

    #[Test]
    public function it_implements_should_broadcast_interface(): void
    {
        $event = new SIRHSyncCompletedEvent(
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            150,
            150,
            0,
            'completed'
        );

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
    }
}
