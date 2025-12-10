<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\TranslationMigrations;

use App\Actions\TranslationMigrations\RollbackTranslationMigrationAction;
use App\DTOs\TranslationMigrations\MigrationResultDTO;
use App\DTOs\TranslationMigrations\RollbackMigrationDTO;
use App\Events\TranslationMigrationRolledBack;
use App\Models\TranslationMigration;
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
class RollbackTranslationMigrationActionTest extends TestCase
{
    use DatabaseTransactions;

    private RollbackTranslationMigrationAction $action;

    private MockInterface $migrationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrationService = Mockery::mock(TranslationMigrationService::class);

        $this->action = new RollbackTranslationMigrationAction(
            $this->migrationService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_rolls_back_migration_successfully(): void
    {
        // Arrange
        Event::fake();

        $migration = TranslationMigration::factory()->completed()->create([
            'interface_origin' => 'web_financer',
            'filename' => 'web_financer_v1.0.0_2025-01-01.json',
            'metadata' => ['backup_path' => 'backups/web_financer/backup.json'],
        ]);

        $dto = new RollbackMigrationDTO(
            migrationId: $migration->id,
            reason: 'Critical bug found'
        );

        $this->migrationService
            ->shouldReceive('rollbackMigration')
            ->with(Mockery::on(fn ($m): bool => $m->id === $migration->id))
            ->andReturn(true);

        // Act
        $result = $this->action->execute($dto);

        // Assert
        $this->assertInstanceOf(MigrationResultDTO::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals($migration->id, $result->migrationId);
        $this->assertNull($result->error);

        Event::assertDispatched(TranslationMigrationRolledBack::class, function ($event) use ($migration): bool {
            return $event->migration->id === $migration->id
                && $event->reason === 'Critical bug found';
        });
    }

    #[Test]
    public function it_fails_to_rollback_pending_migration(): void
    {
        // Arrange
        Event::fake();

        $migration = TranslationMigration::factory()->create([
            'status' => 'pending',
        ]);

        $dto = new RollbackMigrationDTO(
            migrationId: $migration->id,
            reason: 'Test reason'
        );

        // Act
        $result = $this->action->execute($dto);

        // Assert
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Only completed migrations can be rolled back', $result->error);

        Event::assertNotDispatched(TranslationMigrationRolledBack::class);
    }

    #[Test]
    public function it_fails_when_no_backup_path_exists(): void
    {
        // Arrange
        Event::fake();

        $migration = TranslationMigration::factory()->completed()->create([
            'metadata' => [], // No backup_path
        ]);

        $dto = new RollbackMigrationDTO(
            migrationId: $migration->id,
            reason: 'Test reason'
        );

        // Act
        $result = $this->action->execute($dto);

        // Assert
        $this->assertFalse($result->success);
        $this->assertStringContainsString('No backup found', $result->error);

        Event::assertNotDispatched(TranslationMigrationRolledBack::class);
    }

    #[Test]
    public function it_handles_rollback_service_failure(): void
    {
        // Arrange
        Event::fake();

        $migration = TranslationMigration::factory()->completed()->create([
            'metadata' => ['backup_path' => 'backups/test.json'],
        ]);

        $dto = new RollbackMigrationDTO(
            migrationId: $migration->id,
            reason: 'Test reason'
        );

        $this->migrationService
            ->shouldReceive('rollbackMigration')
            ->andReturn(false);

        // Act
        $result = $this->action->execute($dto);

        // Assert
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Rollback failed', $result->error);

        Event::assertNotDispatched(TranslationMigrationRolledBack::class);
    }

    #[Test]
    public function it_uses_database_transaction(): void
    {
        // Arrange
        $migration = TranslationMigration::factory()->completed()->create([
            'metadata' => ['backup_path' => 'backups/test.json'],
        ]);

        $dto = new RollbackMigrationDTO(
            migrationId: $migration->id,
            reason: 'Test reason'
        );

        $this->migrationService
            ->shouldReceive('rollbackMigration')
            ->andThrow(new Exception('Test exception'));

        // Act & Assert
        $this->expectException(Exception::class);
        $this->action->execute($dto);

        // Transaction should be rolled back, migration status unchanged
        $migration->refresh();
        $this->assertEquals('completed', $migration->status);
    }

    #[Test]
    public function it_throws_exception_for_non_existent_migration(): void
    {
        // Arrange
        $dto = new RollbackMigrationDTO(
            migrationId: 99999,
            reason: 'Test reason'
        );

        // Act & Assert
        $this->expectException(ModelNotFoundException::class);
        $this->action->execute($dto);
    }

    #[Test]
    public function it_updates_migration_metadata_with_rollback_info(): void
    {
        // Arrange
        Event::fake();

        $migration = TranslationMigration::factory()->completed()->create([
            'metadata' => [
                'backup_path' => 'backups/test.json',
                'existing' => 'data',
            ],
        ]);

        $dto = new RollbackMigrationDTO(
            migrationId: $migration->id,
            reason: 'Performance issues detected'
        );

        $this->migrationService
            ->shouldReceive('rollbackMigration')
            ->andReturn(true);

        // Act
        $this->action->execute($dto);

        // Assert
        $migration->refresh();
        $this->assertArrayHasKey('rollback_reason', $migration->metadata);
        $this->assertEquals('Performance issues detected', $migration->metadata['rollback_reason']);
        $this->assertEquals('data', $migration->metadata['existing']);
    }

    #[Test]
    public function it_prevents_double_rollback(): void
    {
        // Arrange
        Event::fake();

        $migration = TranslationMigration::factory()->create([
            'status' => 'rolled_back',
            'metadata' => ['backup_path' => 'backups/test.json'],
        ]);

        $dto = new RollbackMigrationDTO(
            migrationId: $migration->id,
            reason: 'Test reason'
        );

        // Act
        $result = $this->action->execute($dto);

        // Assert
        $this->assertFalse($result->success);
        $this->assertStringContainsString('already been rolled back', $result->error);

        Event::assertNotDispatched(TranslationMigrationRolledBack::class);
    }
}
