<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs\TranslationMigrations;

use App\Actions\TranslationMigrations\ApplyTranslationMigrationAction;
use App\DTOs\TranslationMigrations\ApplyMigrationDTO;
use App\DTOs\TranslationMigrations\MigrationResultDTO;
use App\Jobs\TranslationMigrations\ProcessTranslationMigrationJob;
use App\Models\TranslationMigration;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[Group('admin-panel')]
#[Group('translation')]
#[Group('translation-migrations')]
class ProcessTranslationMigrationJobTest extends TestCase
{
    use DatabaseTransactions;

    private MockInterface $applyAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->applyAction = Mockery::mock(ApplyTranslationMigrationAction::class);
        $this->app->instance(ApplyTranslationMigrationAction::class, $this->applyAction);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_processes_migration_successfully(): void
    {
        // Arrange
        Bus::fake();

        $migration = TranslationMigration::factory()->create([
            'status' => 'pending',
        ]);

        $job = new ProcessTranslationMigrationJob(
            $migration->id,
            createBackup: true,
            validateChecksum: true
        );

        $expectedResult = MigrationResultDTO::success(
            migrationId: $migration->id,
            backupPath: 'backups/test.json'
        );

        $this->applyAction
            ->shouldReceive('execute')
            ->with(Mockery::on(function ($dto) use ($migration): bool {
                return $dto instanceof ApplyMigrationDTO
                    && $dto->migrationId === $migration->id
                    && $dto->createBackup
                    && $dto->validateChecksum;
            }))
            ->once()
            ->andReturn($expectedResult);

        // Act
        $job->handle($this->applyAction);

        // Assert - job completed without throwing
        $this->assertTrue(true);
    }

    #[Test]
    public function it_handles_failed_migration(): void
    {
        // Arrange
        Log::shouldReceive('info')
            ->with('ProcessTranslationMigrationJob started', Mockery::any())
            ->once();

        Log::shouldReceive('info')
            ->with('Migration details before processing', Mockery::any())
            ->once();

        Log::shouldReceive('info')
            ->with('Calling ApplyTranslationMigrationAction', Mockery::any())
            ->once();

        Log::shouldReceive('info')
            ->with('ApplyTranslationMigrationAction completed', Mockery::any())
            ->once();

        Log::shouldReceive('info')
            ->with('Migration status after action execution', Mockery::any())
            ->once();

        Log::shouldReceive('error')
            ->with('Translation migration processing failed', Mockery::any())
            ->once();

        Log::shouldReceive('error')
            ->with('ProcessTranslationMigrationJob exception caught', Mockery::any())
            ->once();

        $migration = TranslationMigration::factory()->create([
            'status' => 'pending',
        ]);

        $job = new ProcessTranslationMigrationJob($migration->id);

        $expectedResult = MigrationResultDTO::failure(
            migrationId: $migration->id,
            error: 'Migration failed'
        );

        $this->applyAction
            ->shouldReceive('execute')
            ->once()
            ->andReturn($expectedResult);

        // Act & Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration failed');

        $job->handle($this->applyAction);
    }

    #[Test]
    public function it_retries_on_exception(): void
    {
        // Arrange
        $migration = TranslationMigration::factory()->create([
            'status' => 'pending',
        ]);

        $job = new ProcessTranslationMigrationJob($migration->id);

        $this->applyAction
            ->shouldReceive('execute')
            ->once()
            ->andThrow(new Exception('Database connection lost'));

        // Act & Assert
        $this->expectException(Exception::class);
        $job->handle($this->applyAction);

        // Job should be configured for retry
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(60, $job->backoff);
    }

    #[Test]
    public function it_can_be_dispatched_to_queue(): void
    {
        // Arrange
        Bus::fake();

        $migration = TranslationMigration::factory()->create();

        // Act
        ProcessTranslationMigrationJob::dispatch(
            $migration->id,
            createBackup: true,
            validateChecksum: false
        );

        // Assert
        Bus::assertDispatched(ProcessTranslationMigrationJob::class, function ($job) use ($migration): bool {
            return $job->migrationId === $migration->id
                && $job->createBackup === true
                && $job->validateChecksum === false;
        });
    }

    #[Test]
    public function it_uses_dynamic_queue_based_on_environment(): void
    {
        // Arrange
        $migration = TranslationMigration::factory()->create();

        // Act
        $job = new ProcessTranslationMigrationJob($migration->id);

        // Assert
        // Queue is now dynamic based on environment configuration
        $expectedQueue = config('queue.connections.sqs.queue', 'default');
        $this->assertEquals($expectedQueue, $job->queue);
    }

    #[Test]
    public function it_has_unique_id_based_on_migration(): void
    {
        // Arrange
        $migration = TranslationMigration::factory()->create();

        // Act
        $job = new ProcessTranslationMigrationJob($migration->id);

        // Assert
        $this->assertEquals("migration_{$migration->id}", $job->uniqueId());
    }

    #[Test]
    public function it_implements_should_be_unique(): void
    {
        // Arrange
        $migration = TranslationMigration::factory()->create();
        $job = new ProcessTranslationMigrationJob($migration->id);

        // Assert
        $this->assertContains('Illuminate\Contracts\Queue\ShouldBeUnique', class_implements($job));
    }

    #[Test]
    public function it_logs_successful_processing(): void
    {
        // Arrange
        Log::shouldReceive('info')
            ->with('ProcessTranslationMigrationJob started', Mockery::any())
            ->once();

        Log::shouldReceive('info')
            ->with('Migration details before processing', Mockery::any())
            ->once();

        Log::shouldReceive('info')
            ->with('Calling ApplyTranslationMigrationAction', Mockery::any())
            ->once();

        Log::shouldReceive('info')
            ->with('ApplyTranslationMigrationAction completed', Mockery::any())
            ->once();

        Log::shouldReceive('info')
            ->with('Migration status after action execution', Mockery::any())
            ->once();

        Log::shouldReceive('info')
            ->with('Translation migration processed successfully', Mockery::any())
            ->once();

        $migration = TranslationMigration::factory()->create([
            'status' => 'pending',
        ]);

        $job = new ProcessTranslationMigrationJob($migration->id);

        $expectedResult = MigrationResultDTO::success(
            migrationId: $migration->id
        );

        $this->applyAction
            ->shouldReceive('execute')
            ->once()
            ->andReturn($expectedResult);

        // Act
        $job->handle($this->applyAction);

        // Assert - logs were called
        $this->assertTrue(true);
    }

    #[Test]
    public function it_marks_migration_as_failed_when_job_fails_after_retries(): void
    {
        // Arrange
        $migration = TranslationMigration::factory()->create([
            'status' => 'pending',
        ]);

        $job = new ProcessTranslationMigrationJob($migration->id);

        $exception = new Exception('Action failed after retries');

        // Act
        $job->failed($exception);

        // Assert - migration should be marked as failed
        $migration->refresh();
        $this->assertEquals('failed', $migration->status);
        $this->assertArrayHasKey('job_error', $migration->metadata);
        $this->assertEquals($exception->getMessage(), $migration->metadata['job_error']);
    }

    #[Test]
    public function it_throws_detailed_exception_with_metadata_on_checksum_failure(): void
    {
        // Arrange
        Log::shouldReceive('info')->with(Mockery::any(), Mockery::any());
        Log::shouldReceive('error')->with(Mockery::any(), Mockery::any());

        $migration = TranslationMigration::factory()->create([
            'filename' => 'mobile_2024-01-01_120000.json',
            'interface_origin' => 'mobile',
            'status' => 'pending',
            'checksum' => 'abc123def456',
        ]);

        $job = new ProcessTranslationMigrationJob($migration->id);

        $expectedResult = MigrationResultDTO::failure(
            migrationId: $migration->id,
            error: 'Checksum validation failed for migration',
            metadata: [
                'filename' => 'mobile_2024-01-01_120000.json',
                'interface' => 'mobile',
                'validation_type' => 'checksum',
                'checksum_expected' => 'abc123def456',
            ]
        );

        $this->applyAction
            ->shouldReceive('execute')
            ->once()
            ->andReturn($expectedResult);

        // Act & Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches(
            '/Migration \d+ failed: Checksum validation failed for migration \| Context: .*mobile_2024-01-01_120000\.json.*/'
        );

        $job->handle($this->applyAction);
    }

    #[Test]
    public function it_throws_detailed_exception_with_service_metadata_on_service_failure(): void
    {
        // Arrange
        Log::shouldReceive('info')->with(Mockery::any(), Mockery::any());
        Log::shouldReceive('error')->with(Mockery::any(), Mockery::any());

        $migration = TranslationMigration::factory()->create([
            'filename' => 'web_2024-02-15_140000.json',
            'interface_origin' => 'web_beneficiary',
            'status' => 'failed',
            'metadata' => ['error_detail' => 'S3 download failed'],
        ]);

        $job = new ProcessTranslationMigrationJob($migration->id);

        $expectedResult = MigrationResultDTO::failure(
            migrationId: $migration->id,
            error: 'Migration application failed',
            metadata: [
                'filename' => 'web_2024-02-15_140000.json',
                'interface' => 'web',
                'service_status' => 'failed',
                'service_metadata' => ['error_detail' => 'S3 download failed'],
            ]
        );

        $this->applyAction
            ->shouldReceive('execute')
            ->once()
            ->andReturn($expectedResult);

        // Act & Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches(
            '/Migration \d+ failed: Migration application failed \| Context: .*web_2024-02-15_140000\.json.*S3 download failed.*/'
        );

        $job->handle($this->applyAction);
    }
}
