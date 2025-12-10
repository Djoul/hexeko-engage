<?php

namespace Tests\Unit\Events\Apideck;

use App\Events\Apideck\SIRHBatchProcessedEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

#[Group('apideck')]
class SIRHBatchProcessedEventTest extends TestCase
{
    #[Test]
    public function it_broadcasts_on_correct_private_channel(): void
    {
        $syncId = Uuid::uuid4()->toString();
        $financerId = Uuid::uuid4()->toString();
        $userId = Uuid::uuid4()->toString();
        $batchNumber = 2;
        $processedCount = 45;
        $failedCount = 5;
        $failedRows = [
            ['row' => ['email' => 'test@example.com'], 'error' => 'Email already exists'],
        ];

        $event = new SIRHBatchProcessedEvent(
            $syncId,
            $financerId,
            $userId,
            $batchNumber,
            $processedCount,
            $failedCount,
            $failedRows
        );

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-user.'.$userId, $channels[0]->name);
    }

    #[Test]
    public function it_broadcasts_with_correct_event_name(): void
    {
        $event = new SIRHBatchProcessedEvent(
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            1,
            50,
            0,
            []
        );

        $this->assertEquals('sirh.batch.processed', $event->broadcastAs());
    }

    #[Test]
    public function it_includes_all_required_data_in_broadcast(): void
    {
        $syncId = Uuid::uuid4()->toString();
        $financerId = Uuid::uuid4()->toString();
        $userId = Uuid::uuid4()->toString();
        $batchNumber = 2;
        $processedCount = 45;
        $failedCount = 5;
        $failedRows = [
            ['row' => ['email' => 'test@example.com'], 'error' => 'Email already exists'],
        ];

        $event = new SIRHBatchProcessedEvent(
            $syncId,
            $financerId,
            $userId,
            $batchNumber,
            $processedCount,
            $failedCount,
            $failedRows
        );

        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('sync_id', $broadcastData);
        $this->assertArrayHasKey('batch_number', $broadcastData);
        $this->assertArrayHasKey('processed_count', $broadcastData);
        $this->assertArrayHasKey('failed_count', $broadcastData);
        $this->assertArrayHasKey('failed_rows', $broadcastData);
        $this->assertArrayHasKey('processed_at', $broadcastData);

        $this->assertEquals($syncId, $broadcastData['sync_id']);
        $this->assertEquals($batchNumber, $broadcastData['batch_number']);
        $this->assertEquals($processedCount, $broadcastData['processed_count']);
        $this->assertEquals($failedCount, $broadcastData['failed_count']);
        $this->assertEquals($failedRows, $broadcastData['failed_rows']);
    }

    #[Test]
    public function it_handles_empty_failed_rows(): void
    {
        $event = new SIRHBatchProcessedEvent(
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            1,
            50,
            0,
            []
        );

        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('failed_rows', $broadcastData);
        $this->assertEmpty($broadcastData['failed_rows']);
        $this->assertEquals(0, $broadcastData['failed_count']);
    }

    #[Test]
    public function it_implements_should_broadcast_interface(): void
    {
        $event = new SIRHBatchProcessedEvent(
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            1,
            50,
            0,
            []
        );

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
    }
}
