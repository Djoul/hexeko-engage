<?php

namespace Tests\Unit\Events\Apideck;

use App\Events\ApideckSyncCompleted;
use DateTimeInterface;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('apideck')]
class ApideckSyncCompletedTest extends TestCase
{
    #[Test]
    public function it_verifies_correct_event_class_name_is_apideck_not_apidesk(): void
    {
        // IMPORTANT: Le nom correct de la classe doit être ApideckSyncCompleted (avec 'ck' et non 'sk')
        $this->assertTrue(
            class_exists(ApideckSyncCompleted::class),
            'Event class ApideckSyncCompleted should exist (not ApideskSyncCompleted)'
        );

        // Vérifier que l'ancienne classe avec la typo n'existe plus
        // Note: On vérifie que le fichier n'existe pas au lieu de class_exists pour éviter l'autoload
        $oldClassFile = app_path('Events/ApideskSyncCompleted.php');
        $this->assertFileDoesNotExist(
            $oldClassFile,
            'Old event class file ApideskSyncCompleted.php (with typo) should not exist anymore'
        );
    }

    #[Test]
    public function it_creates_event_with_sync_data(): void
    {
        $financerId = 'test-financer-123';
        $syncData = [
            'created' => 5,
            'updated' => 3,
            'failed' => 1,
            'total' => 9,
            'duration_seconds' => 45,
            'started_at' => '2025-07-26T10:00:00Z',
            'completed_at' => '2025-07-26T10:00:45Z',
        ];

        $event = new ApideckSyncCompleted($financerId, $syncData);

        $this->assertEquals($financerId, $event->financerId);
        $this->assertEquals($syncData, $event->syncData);
    }

    #[Test]
    public function it_has_correct_broadcast_name(): void
    {
        $event = new ApideckSyncCompleted('test-financer', []);

        $this->assertEquals('apideck.sync.completed', $event->broadcastAs());
    }

    #[Test]
    public function it_broadcasts_with_correct_data(): void
    {
        $financerId = 'test-financer-123';
        $syncData = [
            'created' => 10,
            'updated' => 5,
            'failed' => 2,
            'total' => 17,
            'duration_seconds' => 120,
        ];

        $event = new ApideckSyncCompleted($financerId, $syncData);
        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('type', $broadcastData);
        $this->assertArrayHasKey('severity', $broadcastData);
        $this->assertArrayHasKey('financer_id', $broadcastData);
        $this->assertArrayHasKey('sync_data', $broadcastData);
        $this->assertArrayHasKey('timestamp', $broadcastData);
        $this->assertArrayNotHasKey('toast', $broadcastData);
        $this->assertEquals($financerId, $broadcastData['financer_id']);
        $this->assertEquals($syncData, $broadcastData['sync_data']);
    }

    #[Test]
    public function it_implements_should_broadcast_interface(): void
    {
        $event = new ApideckSyncCompleted('test-financer', []);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    #[Test]
    public function it_includes_timestamp_in_event(): void
    {
        $event = new ApideckSyncCompleted('test-financer', []);

        $this->assertNotNull($event->timestamp);
        $this->assertInstanceOf(DateTimeInterface::class, $event->timestamp);
    }

    #[Test]
    public function it_handles_partial_sync_data(): void
    {
        $financerId = 'test-financer-123';
        $partialSyncData = [
            'created' => 5,
            'total' => 5,
        ];

        $event = new ApideckSyncCompleted($financerId, $partialSyncData);

        $this->assertEquals($partialSyncData, $event->syncData);
        $this->assertArrayNotHasKey('updated', $event->syncData);
        $this->assertArrayNotHasKey('failed', $event->syncData);
    }

    #[Test]
    public function it_includes_error_details_when_sync_fails(): void
    {
        $financerId = 'test-financer-123';
        $syncDataWithError = [
            'created' => 0,
            'failed' => 10,
            'total' => 10,
            'error' => 'API rate limit exceeded',
            'error_code' => 'RATE_LIMIT_EXCEEDED',
        ];

        $event = new ApideckSyncCompleted($financerId, $syncDataWithError);

        $this->assertEquals('API rate limit exceeded', $event->syncData['error']);
        $this->assertEquals('RATE_LIMIT_EXCEEDED', $event->syncData['error_code']);
    }

    #[Test]
    public function it_sets_error_type_and_severity_for_sync_errors(): void
    {
        $event = new ApideckSyncCompleted('test-financer', [
            'error' => 'API rate limit exceeded',
            'total' => 0,
        ]);

        $this->assertEquals('sync.error', $event->type);
        $this->assertEquals('error', $event->severity);
    }

    #[Test]
    public function it_sets_partial_type_and_warning_severity_for_partial_sync(): void
    {
        $event = new ApideckSyncCompleted('test-financer', [
            'created' => 5,
            'updated' => 2,
            'failed' => 3,
            'total' => 10,
        ]);

        $this->assertEquals('sync.partial', $event->type);
        $this->assertEquals('warning', $event->severity);
    }

    #[Test]
    public function it_sets_no_changes_type_and_info_severity_when_no_changes(): void
    {
        $event = new ApideckSyncCompleted('test-financer', [
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'total' => 50,
        ]);

        $this->assertEquals('sync.no_changes', $event->type);
        $this->assertEquals('info', $event->severity);
    }

    #[Test]
    public function it_sets_success_type_and_severity_for_successful_sync(): void
    {
        $event = new ApideckSyncCompleted('test-financer', [
            'created' => 10,
            'updated' => 5,
            'failed' => 0,
            'total' => 15,
        ]);

        $this->assertEquals('sync.success', $event->type);
        $this->assertEquals('success', $event->severity);
    }
}
