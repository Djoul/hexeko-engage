<?php

namespace Tests\Unit\Events;

use App\Events\CsvInvitedUsersBatchProcessed;
use App\Events\CsvInvitedUsersImportCompleted;
use App\Events\CsvInvitedUsersImportStarted;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('events')]
#[Group('websocket')]
#[Group('user')]
class CsvImportEventsWebSocketTest extends TestCase
{
    #[Test]
    public function csv_invited_users_import_started_broadcasts_on_user_private_channel(): void
    {
        // Arrange
        $userId = 'user-123';
        $event = new CsvInvitedUsersImportStarted(
            'import-123',
            'financer-456',
            $userId,
            100,
            2
        );

        // Act
        $channels = $event->broadcastOn();

        // Assert
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-user.'.$userId, $channels[0]->name);
        $this->assertEquals('import.started', $event->broadcastAs());

        $broadcastData = $event->broadcastWith();
        $this->assertArrayHasKey('import_id', $broadcastData);
        $this->assertArrayHasKey('financer_id', $broadcastData);
        $this->assertArrayHasKey('total_rows', $broadcastData);
        $this->assertArrayHasKey('total_batches', $broadcastData);
        $this->assertArrayHasKey('started_at', $broadcastData);
    }

    #[Test]
    public function csv_invited_users_import_completed_broadcasts_on_user_private_channel(): void
    {
        // Arrange
        $userId = 'user-456';
        $event = new CsvInvitedUsersImportCompleted(
            'import-789',
            'financer-123',
            $userId,
            100,
            95,
            5,
            'completed'
        );

        // Act
        $channels = $event->broadcastOn();

        // Assert
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-user.'.$userId, $channels[0]->name);
        $this->assertEquals('import.completed', $event->broadcastAs());

        $broadcastData = $event->broadcastWith();
        $this->assertArrayHasKey('import_id', $broadcastData);
        $this->assertArrayHasKey('financer_id', $broadcastData);
        $this->assertArrayHasKey('total_rows', $broadcastData);
        $this->assertArrayHasKey('processed_rows', $broadcastData);
        $this->assertArrayHasKey('failed_rows', $broadcastData);
        $this->assertArrayHasKey('status', $broadcastData);
        $this->assertArrayHasKey('completed_at', $broadcastData);
    }

    #[Test]
    public function csv_invited_users_batch_processed_broadcasts_on_user_private_channel(): void
    {
        // Arrange
        $userId = 'user-789';
        $failedRows = [
            ['row' => ['email' => 'invalid'], 'error' => 'Invalid email format'],
        ];

        $event = new CsvInvitedUsersBatchProcessed(
            'import-456',
            'financer-789',
            $userId,
            1,
            48,
            2,
            $failedRows
        );

        // Act
        $channels = $event->broadcastOn();

        // Assert
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-user.'.$userId, $channels[0]->name);
        $this->assertEquals('batch.processed', $event->broadcastAs());

        $broadcastData = $event->broadcastWith();
        $this->assertArrayHasKey('import_id', $broadcastData);
        $this->assertArrayHasKey('financer_id', $broadcastData);
        $this->assertArrayHasKey('batch_number', $broadcastData);
        $this->assertArrayHasKey('processed_count', $broadcastData);
        $this->assertArrayHasKey('failed_count', $broadcastData);
        $this->assertArrayHasKey('failed_rows', $broadcastData);
        $this->assertArrayHasKey('processed_at', $broadcastData);
        $this->assertEquals($failedRows, $broadcastData['failed_rows']);
    }

    #[Test]
    public function events_broadcast_to_different_users_on_different_channels(): void
    {
        // Arrange
        $user1 = 'user-001';
        $user2 = 'user-002';

        $event1 = new CsvInvitedUsersImportStarted(
            'import-001',
            'financer-001',
            $user1,
            50,
            1
        );

        $event2 = new CsvInvitedUsersImportStarted(
            'import-002',
            'financer-002',
            $user2,
            100,
            2
        );

        // Act
        $channels1 = $event1->broadcastOn();
        $channels2 = $event2->broadcastOn();

        // Assert
        $this->assertNotEquals($channels1[0]->name, $channels2[0]->name);
        $this->assertEquals('private-user.'.$user1, $channels1[0]->name);
        $this->assertEquals('private-user.'.$user2, $channels2[0]->name);
    }
}
