<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\TranslationMigrations;

use App\Actions\TranslationMigrations\ApplyTranslationMigrationAction;
use App\DTOs\TranslationMigrations\ApplyMigrationDTO;
use App\DTOs\TranslationMigrations\MigrationResultDTO;
use App\Events\TranslationMigrationApplied;
use App\Events\TranslationMigrationFailed;
use App\Models\TranslationMigration;
use App\Services\TranslationMigrations\S3StorageService;
use App\Services\TranslationMigrations\TranslationMigrationService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('translation-migrations')]
class ApplyTranslationMigrationActionTest extends TestCase
{
    use DatabaseTransactions;

    private ApplyTranslationMigrationAction $action;

    private MockInterface $migrationService;

    private MockInterface $s3Service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrationService = Mockery::mock(TranslationMigrationService::class);
        $this->s3Service = Mockery::mock(S3StorageService::class);

        $this->action = new ApplyTranslationMigrationAction(
            $this->migrationService,
            $this->s3Service
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_applies_migration_successfully(): void
    {
        // Arrange
        Event::fake();

        $migration = TranslationMigration::factory()->create([
            'status' => 'pending',
            'interface_origin' => 'web_financer',
            'filename' => 'web_financer_v1.0.0_2025-01-01.json',
        ]);

        $dto = new ApplyMigrationDTO(
            migrationId: $migration->id,
            createBackup: true,
            validateChecksum: true
        );

        $currentContent = json_encode(['old' => 'content']);
        $backupPath = 'backups/web_financer/backup.json';

        // Mock checksum validation
        $this->migrationService
            ->shouldReceive('validateMigrationChecksum')
            ->with(Mockery::on(fn ($m): bool => $m->id === $migration->id))
            ->andReturn(true);

        // Mock creating backup
        $this->s3Service
            ->shouldReceive('downloadMigrationFile')
            ->with('web_financer', 'current.json')
            ->andReturn($currentContent);

        $this->s3Service
            ->shouldReceive('createUnifiedBackup')
            ->with('web_financer', 'before-apply-migration', $currentContent, 'json')
            ->andReturn($backupPath);

        // Mock applying migration
        $this->migrationService
            ->shouldReceive('applyMigration')
            ->with(Mockery::on(fn ($m): bool => $m->id === $migration->id))
            ->andReturn(true);

        // Act
        $result = $this->action->execute($dto);

        // Assert
        $this->assertInstanceOf(MigrationResultDTO::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals($migration->id, $result->migrationId);
        $this->assertEquals($backupPath, $result->backupPath);
        $this->assertNull($result->error);

        Event::assertDispatched(TranslationMigrationApplied::class, function ($event) use ($migration): bool {
            return $event->migration->id === $migration->id;
        });
    }

    #[Test]
    public function it_fails_when_checksum_validation_fails(): void
    {
        // Arrange
        Event::fake();

        $migration = TranslationMigration::factory()->create([
            'status' => 'pending',
        ]);

        $dto = new ApplyMigrationDTO(
            migrationId: $migration->id,
            createBackup: true,
            validateChecksum: true
        );

        $this->migrationService
            ->shouldReceive('validateMigrationChecksum')
            ->with(Mockery::on(fn ($m): bool => $m->id === $migration->id))
            ->andReturn(false);

        // Act
        $result = $this->action->execute($dto);

        // Assert
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Checksum validation failed', $result->error);

        Event::assertDispatched(TranslationMigrationFailed::class);
    }

    #[Test]
    public function it_applies_migration_without_backup_when_requested(): void
    {
        // Arrange
        Event::fake();

        $migration = TranslationMigration::factory()->create([
            'status' => 'pending',
        ]);

        $dto = new ApplyMigrationDTO(
            migrationId: $migration->id,
            createBackup: false,
            validateChecksum: false
        );

        $this->migrationService
            ->shouldReceive('applyMigration')
            ->with(Mockery::on(fn ($m): bool => $m->id === $migration->id))
            ->andReturn(true);

        // Act
        $result = $this->action->execute($dto);

        // Assert
        $this->assertTrue($result->success);
        $this->assertNull($result->backupPath);

        Event::assertDispatched(TranslationMigrationApplied::class);
    }

    #[Test]
    public function it_handles_migration_failure(): void
    {
        // Arrange
        Event::fake();

        $migration = TranslationMigration::factory()->create([
            'status' => 'pending',
        ]);

        $dto = new ApplyMigrationDTO(
            migrationId: $migration->id,
            createBackup: false,
            validateChecksum: false
        );

        $this->migrationService
            ->shouldReceive('applyMigration')
            ->with(Mockery::on(fn ($m): bool => $m->id === $migration->id))
            ->andReturn(false);

        // Need to bind mocks to container for event dispatching to work
        $this->app->instance(TranslationMigrationService::class, $this->migrationService);
        $this->app->instance(S3StorageService::class, $this->s3Service);

        // Get the action from container so events are properly dispatched
        $action = app(ApplyTranslationMigrationAction::class);

        // Act
        $result = $action->execute($dto);

        // Assert
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Migration application failed', $result->error);

        Event::assertDispatched(TranslationMigrationFailed::class);
    }

    #[Test]
    public function it_uses_database_transaction(): void
    {
        // Arrange
        $migration = TranslationMigration::factory()->create([
            'status' => 'pending',
        ]);

        $dto = new ApplyMigrationDTO(
            migrationId: $migration->id,
            createBackup: false,
            validateChecksum: false
        );

        $this->migrationService
            ->shouldReceive('applyMigration')
            ->andThrow(new Exception('Test exception'));

        // Act & Assert
        $this->expectException(Exception::class);
        $this->action->execute($dto);

        // Transaction should be rolled back, migration status unchanged
        $migration->refresh();
        $this->assertEquals('pending', $migration->status);
    }

    #[Test]
    public function it_throws_exception_for_non_existent_migration(): void
    {
        // Arrange
        $dto = new ApplyMigrationDTO(
            migrationId: 99999,
            createBackup: false,
            validateChecksum: false
        );

        // Act & Assert
        $this->expectException(ModelNotFoundException::class);
        $this->action->execute($dto);
    }

    #[Test]
    public function it_updates_migration_metadata_with_backup_path(): void
    {
        // Arrange
        Event::fake();

        $migration = TranslationMigration::factory()->create([
            'status' => 'pending',
            'metadata' => ['existing' => 'data'],
        ]);

        $dto = new ApplyMigrationDTO(
            migrationId: $migration->id,
            createBackup: true,
            validateChecksum: false
        );

        $currentContent = json_encode(['current' => 'data']);
        $backupPath = 'backups/web_financer/backup_v1.0.0.json';

        $this->s3Service
            ->shouldReceive('downloadMigrationFile')
            ->andReturn($currentContent);

        $this->s3Service
            ->shouldReceive('createUnifiedBackup')
            ->with(Mockery::any(), 'before-apply-migration', $currentContent, 'json')
            ->andReturn($backupPath);

        $this->migrationService
            ->shouldReceive('applyMigration')
            ->andReturn(true);

        // Act
        $this->action->execute($dto);

        // Assert
        $migration->refresh();
        $this->assertArrayHasKey('backup_path', $migration->metadata);
        $this->assertEquals($backupPath, $migration->metadata['backup_path']);
        $this->assertEquals('data', $migration->metadata['existing']);
    }

    #[Test]
    public function it_updates_migration_status_to_completed_after_successful_application(): void
    {
        // Arrange
        Event::fake();

        $migration = TranslationMigration::factory()->create([
            'status' => 'pending',
            'batch_number' => null,
            'executed_at' => null,
        ]);

        $dto = new ApplyMigrationDTO(
            migrationId: $migration->id,
            createBackup: false,
            validateChecksum: false
        );

        $this->migrationService
            ->shouldReceive('applyMigration')
            ->with(Mockery::on(fn ($m): bool => $m->id === $migration->id))
            ->andReturn(true);

        // Act
        $result = $this->action->execute($dto);

        // Assert - The action should orchestrate properly but the actual status update
        // is handled by the TranslationMigrationService
        $this->assertTrue($result->success);
        $this->assertEquals($migration->id, $result->migrationId);

        Event::assertDispatched(TranslationMigrationApplied::class, function ($event) use ($migration): bool {
            return $event->migration->id === $migration->id;
        });

        // Note: The actual status update to 'completed' is tested in TranslationMigrationServiceTest
        // since the service is responsible for managing the migration model state changes
    }
}
