<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Actions\Translation\ImportTranslationsAction;
use App\Enums\OrigineInterfaces;
use App\Models\TranslationMigration;
use App\Services\TranslationMigrations\S3StorageService;
use App\Services\TranslationMigrations\TranslationMigrationService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[Group('admin-panel')]
#[Group('translation')]
class TranslationMigrationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private TranslationMigrationService $service;

    private MockInterface $s3StorageService;

    private MockInterface $importTranslationsAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->s3StorageService = Mockery::mock(S3StorageService::class);
        $this->importTranslationsAction = Mockery::mock(ImportTranslationsAction::class);

        // Prevent writing real files during backups triggered by imports
        Storage::fake('local');

        $this->service = new TranslationMigrationService(
            $this->s3StorageService,
            $this->importTranslationsAction
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_tracks_new_migration_successfully(): void
    {
        // Arrange
        $interface = OrigineInterfaces::WEB_FINANCER;
        $filename = 'web_financer_v1.0.0_2025-01-01.json';
        $version = 'v1.0.0';
        $checksum = hash('sha256', 'content');
        $metadata = ['keys_added' => 5, 'keys_modified' => 2];

        $this->s3StorageService
            ->shouldReceive('migrationFileExists')
            ->with($interface, $filename)
            ->andReturn(true);

        $this->s3StorageService
            ->shouldReceive('getFileChecksum')
            ->with($interface, $filename)
            ->andReturn($checksum);

        // Act
        $migration = $this->service->trackMigration(
            $filename,
            $interface,
            $version,
            $checksum,
            $metadata
        );

        // Assert
        $this->assertInstanceOf(TranslationMigration::class, $migration);
        $this->assertEquals($filename, $migration->filename);
        $this->assertEquals($interface, $migration->interface_origin);
        $this->assertEquals($version, $migration->version);
        $this->assertEquals($checksum, $migration->checksum);
        $this->assertEquals($metadata, $migration->metadata);
        $this->assertEquals('pending', $migration->status);
    }

    #[Test]
    public function it_throws_exception_when_tracking_non_existent_file(): void
    {
        // Arrange
        $interface = OrigineInterfaces::WEB_FINANCER;
        $filename = 'non_existent.json';

        $this->s3StorageService
            ->shouldReceive('migrationFileExists')
            ->with($interface, $filename)
            ->andReturn(false);

        // Act & Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Migration file does not exist in S3: {$filename}");

        $this->service->trackMigration(
            $filename,
            $interface,
            'v1.0.0',
            'checksum',
            []
        );
    }

    #[Test]
    public function it_applies_migration_successfully(): void
    {
        // Arrange
        $migration = TranslationMigration::factory()->create([
            'status' => 'pending',
            'batch_number' => null,
        ]);

        $payload = [
            'interface' => $migration->interface_origin,
            'translations' => [
                'messages.existing' => [
                    'fr-FR' => 'Bonjour tout le monde',
                ],
            ],
        ];

        $content = json_encode($payload);

        $this->s3StorageService
            ->shouldReceive('downloadMigrationFile')
            ->with($migration->interface_origin, $migration->filename)
            ->andReturn($content);

        $this->importTranslationsAction
            ->shouldReceive('execute')
            ->once()
            ->with(
                [
                    'interface' => $migration->interface_origin,
                    'translations' => [
                        'messages.existing' => [
                            'fr-FR' => 'Bonjour tout le monde',
                        ],
                    ],
                    'update_existing_values' => true, // ← CRITICAL: Must be true for migrations
                ],
                $migration->interface_origin,
                false
            )
            ->andReturn([
                'success' => true,
                'summary' => [
                    'new_keys_created' => 1,
                    'values_updated' => 0,
                ],
            ]);

        // Act
        $result = $this->service->applyMigration($migration);

        // Assert
        $this->assertTrue($result);

        $migration->refresh();
        $this->assertEquals('completed', $migration->status);
        $this->assertNotNull($migration->batch_number);
        $this->assertNotNull($migration->executed_at);

        $this->assertArrayHasKey('summary', $migration->metadata);
        $this->assertEquals(1, $migration->metadata['summary']['new_keys_created']);
        $this->assertEquals(0, $migration->metadata['summary']['values_updated']);
    }

    #[Test]
    public function it_handles_migration_failure(): void
    {
        // Arrange
        $migration = TranslationMigration::factory()->create([
            'status' => 'pending',
        ]);

        $this->s3StorageService
            ->shouldReceive('downloadMigrationFile')
            ->with($migration->interface_origin, $migration->filename)
            ->andThrow(new Exception('Download failed'));

        // Act
        $result = $this->service->applyMigration($migration);

        // Assert
        $this->assertFalse($result);
        $migration->refresh();
        $this->assertEquals('failed', $migration->status);
        $this->assertArrayHasKey('error', $migration->metadata);
    }

    #[Test]
    public function it_rolls_back_migration_successfully(): void
    {
        // Arrange
        $migration = TranslationMigration::factory()->completed()->create([
            'batch_number' => 1,
        ]);

        $backupPath = "backups/{$migration->interface_origin}/backup.json";

        // Assume backup exists in metadata
        $migration->update([
            'metadata' => array_merge($migration->metadata ?? [], ['backup_path' => $backupPath]),
        ]);

        $this->s3StorageService
            ->shouldReceive('downloadMigrationFile')
            ->with($migration->interface_origin, basename($backupPath))
            ->andReturn(json_encode(['old' => 'data']));

        // Act
        $result = $this->service->rollbackMigration($migration);

        // Assert
        $this->assertTrue($result);
        $migration->refresh();
        $this->assertEquals('rolled_back', $migration->status);
        $this->assertNotNull($migration->rolled_back_at);
    }

    #[Test]
    public function it_gets_pending_migrations(): void
    {
        // Arrange
        $initialPendingCount = TranslationMigration::where('status', 'pending')->count();

        // Create new test migrations
        TranslationMigration::factory()->count(3)->create(['status' => 'pending']);
        TranslationMigration::factory()->count(2)->completed()->create();

        // Act
        $pendingMigrations = $this->service->getPendingMigrations();

        // Assert
        $this->assertInstanceOf(Collection::class, $pendingMigrations);
        $this->assertCount($initialPendingCount + 3, $pendingMigrations);
        $pendingMigrations->each(function ($migration): void {
            $this->assertEquals('pending', $migration->status);
        });
    }

    #[Test]
    public function it_gets_migrations_for_specific_interface(): void
    {
        // Arrange
        $interface = OrigineInterfaces::WEB_FINANCER;
        $initialCount = TranslationMigration::where('interface_origin', $interface)->count();

        TranslationMigration::factory()->count(3)->create([
            'interface_origin' => $interface,
        ]);

        TranslationMigration::factory()->count(2)->create([
            'interface_origin' => OrigineInterfaces::WEB_BENEFICIARY,
        ]);

        // Act
        $migrations = $this->service->getMigrationsForInterface($interface);

        // Assert
        $this->assertCount($initialCount + 3, $migrations);
        $migrations->each(function ($migration) use ($interface): void {
            $this->assertEquals($interface, $migration->interface_origin);
        });
    }

    #[Test]
    public function it_checks_if_migration_exists(): void
    {
        // Arrange
        TranslationMigration::factory()->create([
            'filename' => 'existing.json',
        ]);

        // Act
        $exists = $this->service->migrationExists('existing.json');
        $notExists = $this->service->migrationExists('non_existing.json');

        // Assert
        $this->assertTrue($exists);
        $this->assertFalse($notExists);
    }

    #[Test]
    public function it_gets_latest_batch_number(): void
    {
        // Arrange
        TranslationMigration::factory()->create(['batch_number' => 1]);
        TranslationMigration::factory()->create(['batch_number' => 3]);
        TranslationMigration::factory()->create(['batch_number' => 2]);

        // Act
        $latestBatch = $this->service->getLatestBatchNumber();

        // Assert
        $this->assertEquals(3, $latestBatch);
    }

    #[Test]
    public function it_returns_zero_for_latest_batch_when_no_migrations(): void
    {
        // Arrange - Clean the table to ensure no migrations exist for this test
        TranslationMigration::query()->delete();

        // Act
        $latestBatch = $this->service->getLatestBatchNumber();

        // Assert
        $this->assertEquals(0, $latestBatch);
    }

    #[Test]
    public function it_syncs_migrations_from_s3(): void
    {
        // Arrange
        $interface = OrigineInterfaces::WEB_FINANCER;

        $s3Files = collect([
            'migrations/web_financer/web_financer_v1.0.0_2025-01-01.json',
            'migrations/web_financer/web_financer_v1.0.1_2025-01-02.json',
        ]);

        $this->s3StorageService
            ->shouldReceive('listMigrationFiles')
            ->with($interface)
            ->andReturn($s3Files);

        // Mock downloadMigrationFile calls for checksum generation
        $this->s3StorageService
            ->shouldReceive('downloadMigrationFile')
            ->with($interface, 'web_financer_v1.0.0_2025-01-01.json')
            ->andReturn('{"test": "content1"}');

        $this->s3StorageService
            ->shouldReceive('downloadMigrationFile')
            ->with($interface, 'web_financer_v1.0.1_2025-01-02.json')
            ->andReturn('{"test": "content2"}');

        // Mock migrationFileExists for the new migration being tracked
        $this->s3StorageService
            ->shouldReceive('migrationFileExists')
            ->with($interface, 'web_financer_v1.0.1_2025-01-02.json')
            ->andReturn(true);

        // Create one existing migration with checksum matching the downloaded content
        TranslationMigration::factory()->create([
            'filename' => 'web_financer_v1.0.0_2025-01-01.json',
            'interface_origin' => $interface,
            'checksum' => hash('sha256', '{"test": "content1"}'),
        ]);

        // Act
        $synced = $this->service->syncMigrationsFromS3($interface);

        // Assert
        $this->assertEquals(1, $synced); // Only one new migration should be tracked
        $this->assertTrue(TranslationMigration::where('filename', 'web_financer_v1.0.1_2025-01-02.json')->exists());
    }

    #[Test]
    public function it_validates_migration_checksum(): void
    {
        // Arrange
        $migration = TranslationMigration::factory()->create([
            'checksum' => 'original_checksum',
        ]);

        $this->s3StorageService
            ->shouldReceive('getFileChecksum')
            ->with($migration->interface_origin, $migration->filename)
            ->andReturn('different_checksum');

        // Act
        $isValid = $this->service->validateMigrationChecksum($migration);

        // Assert
        $this->assertFalse($isValid);
    }

    #[Test]
    public function it_processes_batch_of_migrations(): void
    {
        // Arrange - Clean table first to ensure batch_number starts at 1
        TranslationMigration::query()->delete();

        $migrations = TranslationMigration::factory()->count(3)->create([
            'status' => 'pending',
        ]);

        $this->s3StorageService
            ->shouldReceive('downloadMigrationFile')
            ->times(3)
            ->andReturn(json_encode([
                'interface' => 'web_financer',
                'translations' => ['test' => ['fr-FR' => 'data']],
            ]));

        $this->importTranslationsAction
            ->shouldReceive('execute')
            ->times(3)
            ->andReturn([
                'success' => true,
                'summary' => [
                    'new_keys_created' => 1,
                    'values_updated' => 0,
                ],
            ]);

        // Act
        $results = $this->service->processBatch($migrations);

        // Assert
        $this->assertCount(3, $results);
        $this->assertTrue($results->every(fn ($result): bool => $result));

        $migrations->each(function ($migration): void {
            $migration->refresh();
            $this->assertEquals('completed', $migration->status);
            $this->assertEquals(1, $migration->batch_number);
        });
    }
}
