<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs\TranslationMigrations;

use App\Enums\OrigineInterfaces;
use App\Jobs\TranslationMigrations\ProcessTranslationMigrationJob;
use App\Jobs\TranslationMigrations\SyncTranslationMigrationsJob;
use App\Services\TranslationMigrations\TranslationMigrationService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('admin-panel')]
#[Group('translation')]
class SyncTranslationMigrationsJobTest extends TestCase
{
    use DatabaseTransactions;

    private MockInterface $migrationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrationService = Mockery::mock(TranslationMigrationService::class);
        $this->app->instance(TranslationMigrationService::class, $this->migrationService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_syncs_migrations_successfully(): void
    {
        // Arrange
        Bus::fake();

        $interface = OrigineInterfaces::WEB_FINANCER;
        $job = new SyncTranslationMigrationsJob($interface, autoProcess: false);

        $this->migrationService
            ->shouldReceive('syncMigrationsFromS3')
            ->with($interface)
            ->once()
            ->andReturn(3);

        // Act
        $job->handle($this->migrationService);

        // Assert
        Bus::assertNotDispatched(ProcessTranslationMigrationJob::class);
    }

    #[Test]
    public function it_dispatches_processing_jobs_when_auto_process_enabled(): void
    {
        // Arrange
        Bus::fake();

        Log::shouldReceive('info')
            ->once()
            ->with('Syncing translation migrations from S3', Mockery::any());

        Log::shouldReceive('info')
            ->once()
            ->with('Translation migrations synced', Mockery::any());

        Log::shouldReceive('info')
            ->once()
            ->with('Dispatching processing jobs for pending migrations', Mockery::on(function (array $data): bool {
                return $data['pending_count'] === 2;
            }));

        $interface = OrigineInterfaces::WEB_FINANCER;
        $job = new SyncTranslationMigrationsJob($interface, autoProcess: true);

        $this->migrationService
            ->shouldReceive('syncMigrationsFromS3')
            ->with($interface)
            ->once()
            ->andReturn(2);

        $this->migrationService
            ->shouldReceive('getPendingMigrations')
            ->once()
            ->andReturn(collect([
                (object) ['id' => 1, 'interface_origin' => $interface],
                (object) ['id' => 2, 'interface_origin' => $interface],
            ]));

        // Act
        $job->handle($this->migrationService);

        // Assert - Verify jobs were dispatched
        Bus::assertDispatched(ProcessTranslationMigrationJob::class, 2);
        Bus::assertDispatched(ProcessTranslationMigrationJob::class, function (ProcessTranslationMigrationJob $job): bool {
            return $job->migrationId === 1 && $job->createBackup && $job->validateChecksum;
        });
        Bus::assertDispatched(ProcessTranslationMigrationJob::class, function (ProcessTranslationMigrationJob $job): bool {
            return $job->migrationId === 2 && $job->createBackup && $job->validateChecksum;
        });
    }

    #[Test]
    public function it_logs_sync_results(): void
    {
        // Arrange
        Log::shouldReceive('info')
            ->once()
            ->with('Syncing translation migrations from S3', Mockery::any());

        Log::shouldReceive('info')
            ->once()
            ->with('Translation migrations synced', Mockery::any());

        $interface = OrigineInterfaces::WEB_BENEFICIARY;
        $job = new SyncTranslationMigrationsJob($interface);

        $this->migrationService
            ->shouldReceive('syncMigrationsFromS3')
            ->andReturn(5);

        // Act
        $job->handle($this->migrationService);

        // Assert - logs were called
        $this->assertTrue(true);
    }

    #[Test]
    public function it_handles_sync_failure(): void
    {
        // Arrange
        $interface = OrigineInterfaces::MOBILE;
        $job = new SyncTranslationMigrationsJob($interface);

        $this->migrationService
            ->shouldReceive('syncMigrationsFromS3')
            ->once()
            ->andThrow(new Exception('S3 connection failed'));

        // Act & Assert
        $this->expectException(Exception::class);
        $job->handle($this->migrationService);
    }

    #[Test]
    public function it_can_be_dispatched_to_queue(): void
    {
        // This test verifies the job can be created and has proper queue configuration
        // Actual dispatching is tested through integration tests

        // Arrange
        $interface = OrigineInterfaces::WEB_FINANCER;

        // Act
        $job = new SyncTranslationMigrationsJob($interface, true);

        // Assert
        // Queue is now dynamic based on environment configuration
        $expectedQueue = config('queue.connections.sqs.queue', 'default');
        $this->assertEquals($expectedQueue, $job->queue);
        $this->assertEquals($interface, $job->interface);
        $this->assertTrue($job->autoProcess);
    }

    #[Test]
    public function it_uses_dynamic_queue_based_on_environment(): void
    {
        // Arrange & Act
        $job = new SyncTranslationMigrationsJob(OrigineInterfaces::WEB_FINANCER);

        // Assert
        // Queue is now dynamic based on environment configuration
        $expectedQueue = config('queue.connections.sqs.queue', 'default');
        $this->assertEquals($expectedQueue, $job->queue);
    }

    #[Test]
    public function it_has_unique_id_based_on_interface(): void
    {
        // Arrange
        $interface = OrigineInterfaces::WEB_FINANCER;

        // Act
        $job = new SyncTranslationMigrationsJob($interface);

        // Assert
        $this->assertEquals("sync_{$interface}", $job->uniqueId());
    }

    #[Test]
    public function it_implements_should_be_unique(): void
    {
        // Arrange
        $job = new SyncTranslationMigrationsJob(OrigineInterfaces::WEB_FINANCER);

        // Assert
        $this->assertContains('Illuminate\Contracts\Queue\ShouldBeUnique', class_implements($job));
    }

    #[Test]
    public function it_only_processes_pending_migrations_for_interface(): void
    {
        // Arrange
        Bus::fake();

        $interface = OrigineInterfaces::WEB_FINANCER;
        $otherInterface = OrigineInterfaces::WEB_BENEFICIARY;

        Log::shouldReceive('info')
            ->once()
            ->with('Syncing translation migrations from S3', Mockery::any());

        Log::shouldReceive('info')
            ->once()
            ->with('Translation migrations synced', Mockery::any());

        Log::shouldReceive('info')
            ->once()
            ->with('Dispatching processing jobs for pending migrations', Mockery::on(function (array $data): bool {
                return $data['pending_count'] === 2; // Only 2 migrations for matching interface
            }));

        $job = new SyncTranslationMigrationsJob($interface, autoProcess: true);

        $this->migrationService
            ->shouldReceive('syncMigrationsFromS3')
            ->andReturn(3);

        $this->migrationService
            ->shouldReceive('getPendingMigrations')
            ->once()
            ->andReturn(collect([
                (object) ['id' => 1, 'interface_origin' => $interface],
                (object) ['id' => 2, 'interface_origin' => $otherInterface],
                (object) ['id' => 3, 'interface_origin' => $interface],
            ]));

        // Act
        $job->handle($this->migrationService);

        // Assert - Only 2 jobs dispatched for matching interface (ids 1 and 3)
        Bus::assertDispatched(ProcessTranslationMigrationJob::class, 2);
        Bus::assertDispatched(ProcessTranslationMigrationJob::class, function (ProcessTranslationMigrationJob $job): bool {
            return $job->migrationId === 1;
        });
        Bus::assertDispatched(ProcessTranslationMigrationJob::class, function (ProcessTranslationMigrationJob $job): bool {
            return $job->migrationId === 3;
        });
        // Ensure job for other interface (id=2) was NOT dispatched
        Bus::assertNotDispatched(ProcessTranslationMigrationJob::class, function (ProcessTranslationMigrationJob $job): bool {
            return $job->migrationId === 2;
        });
    }
}
