<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\OrigineInterfaces;
use App\Models\TranslationMigration;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('admin-panel')]
#[Group('translation')]
class TranslationMigrationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_creates_migration_record_with_valid_data(): void
    {
        // Arrange
        $data = [
            'filename' => 'web_financer_v1.0.0_2025-08-27.json',
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v1.0.0',
            'checksum' => hash('sha256', 'test-content'),
            'metadata' => [
                'user_id' => 1,
                'source' => 'manual_export',
                'environment' => 'production',
                'keys_count' => 250,
                'locales' => ['fr', 'en', 'es'],
            ],
            'status' => 'pending',
        ];

        // Act
        $migration = TranslationMigration::create($data);

        // Assert
        $this->assertInstanceOf(TranslationMigration::class, $migration);
        $this->assertEquals($data['filename'], $migration->filename);
        $this->assertEquals($data['interface_origin'], $migration->interface_origin);
        $this->assertEquals($data['version'], $migration->version);
        $this->assertEquals($data['checksum'], $migration->checksum);
        $this->assertEquals($data['metadata'], $migration->metadata);
        $this->assertEquals($data['status'], $migration->status);
        $this->assertDatabaseHas('translation_migrations', [
            'filename' => $data['filename'],
            'interface_origin' => $data['interface_origin'],
            'version' => $data['version'],
        ]);
    }

    #[Test]
    public function it_enforces_unique_filename_constraint(): void
    {
        // Arrange
        $filename = 'web_financer_v1.0.0_2025-08-27.json';
        TranslationMigration::create([
            'filename' => $filename,
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v1.0.0',
            'checksum' => hash('sha256', 'content-1'),
            'metadata' => [],
            'status' => 'pending',
        ]);

        // Act & Assert
        $this->expectException(QueryException::class);

        TranslationMigration::create([
            'filename' => $filename, // Same filename - should fail
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v1.0.1',
            'checksum' => hash('sha256', 'content-2'),
            'metadata' => [],
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function it_scopes_pending_migrations(): void
    {
        // Arrange
        $initialPendingCount = TranslationMigration::pending()->count();

        $pending1 = TranslationMigration::create([
            'filename' => 'pending_1.json',
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v1.0.0',
            'checksum' => hash('sha256', 'content-1'),
            'metadata' => [],
            'status' => 'pending',
        ]);

        $pending2 = TranslationMigration::create([
            'filename' => 'pending_2.json',
            'interface_origin' => OrigineInterfaces::WEB_BENEFICIARY,
            'version' => 'v1.0.0',
            'checksum' => hash('sha256', 'content-2'),
            'metadata' => [],
            'status' => 'pending',
        ]);

        TranslationMigration::create([
            'filename' => 'completed_1.json',
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v0.9.0',
            'checksum' => hash('sha256', 'content-3'),
            'metadata' => [],
            'status' => 'completed',
            'executed_at' => now(),
        ]);

        TranslationMigration::create([
            'filename' => 'failed_1.json',
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v0.8.0',
            'checksum' => hash('sha256', 'content-4'),
            'metadata' => [],
            'status' => 'failed',
            'error_message' => 'Test error',
        ]);

        // Act
        $pendingMigrations = TranslationMigration::pending()->get();

        // Assert
        $this->assertCount($initialPendingCount + 2, $pendingMigrations);
        $this->assertTrue($pendingMigrations->contains($pending1));
        $this->assertTrue($pendingMigrations->contains($pending2));
        $this->assertTrue($pendingMigrations->every(fn ($m): bool => $m->status === 'pending'));
    }

    #[Test]
    public function it_generates_correct_s3_path(): void
    {
        // Arrange
        $migration = TranslationMigration::create([
            'filename' => 'web_financer_v1.0.0_2025-08-27.json',
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v1.0.0',
            'checksum' => hash('sha256', 'test-content'),
            'metadata' => [],
            'status' => 'pending',
        ]);

        // Act
        $s3Path = $migration->getS3Path();

        // Assert
        $expectedPath = 'migrations/web_financer/web_financer_v1.0.0_2025-08-27.json';
        $this->assertEquals($expectedPath, $s3Path);
    }

    #[Test]
    public function it_scopes_by_interface_origin(): void
    {
        // Arrange
        $initialFinancerCount = TranslationMigration::forInterface(OrigineInterfaces::WEB_FINANCER)->count();
        $initialBeneficiaryCount = TranslationMigration::forInterface(OrigineInterfaces::WEB_BENEFICIARY)->count();

        $financer1 = TranslationMigration::create([
            'filename' => 'web_financer_1.json',
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v1.0.0',
            'checksum' => hash('sha256', 'content-1'),
            'metadata' => [],
            'status' => 'completed',
        ]);

        $financer2 = TranslationMigration::create([
            'filename' => 'web_financer_2.json',
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v1.0.1',
            'checksum' => hash('sha256', 'content-2'),
            'metadata' => [],
            'status' => 'completed',
        ]);

        $beneficiary1 = TranslationMigration::create([
            'filename' => 'web_beneficiary_1.json',
            'interface_origin' => OrigineInterfaces::WEB_BENEFICIARY,
            'version' => 'v1.0.0',
            'checksum' => hash('sha256', 'content-3'),
            'metadata' => [],
            'status' => 'completed',
        ]);

        // Act
        $financerMigrations = TranslationMigration::forInterface(OrigineInterfaces::WEB_FINANCER)->get();
        $beneficiaryMigrations = TranslationMigration::forInterface(OrigineInterfaces::WEB_BENEFICIARY)->get();

        // Assert
        $this->assertCount($initialFinancerCount + 2, $financerMigrations);
        $this->assertCount($initialBeneficiaryCount + 1, $beneficiaryMigrations);
        $this->assertTrue($financerMigrations->contains($financer1));
        $this->assertTrue($financerMigrations->contains($financer2));
        $this->assertTrue($beneficiaryMigrations->contains($beneficiary1));
        $this->assertTrue($financerMigrations->every(fn ($m): bool => $m->interface_origin === OrigineInterfaces::WEB_FINANCER));
        $this->assertTrue($beneficiaryMigrations->every(fn ($m): bool => $m->interface_origin === OrigineInterfaces::WEB_BENEFICIARY));
    }

    #[Test]
    public function it_scopes_completed_migrations(): void
    {
        // Arrange
        $initialCompletedCount = TranslationMigration::completed()->count();

        $completed1 = TranslationMigration::create([
            'filename' => 'completed_1.json',
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v1.0.0',
            'checksum' => hash('sha256', 'content-1'),
            'metadata' => [],
            'status' => 'completed',
            'executed_at' => now()->subHours(2),
        ]);

        $completed2 = TranslationMigration::create([
            'filename' => 'completed_2.json',
            'interface_origin' => OrigineInterfaces::WEB_BENEFICIARY,
            'version' => 'v1.0.1',
            'checksum' => hash('sha256', 'content-2'),
            'metadata' => [],
            'status' => 'completed',
            'executed_at' => now()->subHour(),
        ]);

        TranslationMigration::create([
            'filename' => 'pending_1.json',
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v1.0.2',
            'checksum' => hash('sha256', 'content-3'),
            'metadata' => [],
            'status' => 'pending',
        ]);

        // Act
        $completedMigrations = TranslationMigration::completed()->get();

        // Assert
        $this->assertCount($initialCompletedCount + 2, $completedMigrations);
        $this->assertTrue($completedMigrations->contains($completed1));
        $this->assertTrue($completedMigrations->contains($completed2));
        $this->assertTrue($completedMigrations->every(fn ($m): bool => $m->status === 'completed'));
        $this->assertTrue($completedMigrations->every(fn ($m): bool => $m->executed_at !== null));
    }

    #[Test]
    public function it_gets_latest_completed_migration_for_interface(): void
    {
        // Arrange - Use future timestamps to ensure our test data is the most recent
        $oldExecutedAt = now()->addMinutes(10);
        $latestExecutedAt = now()->addMinutes(20);

        TranslationMigration::create([
            'filename' => 'old.json',
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v0.9.0',
            'checksum' => hash('sha256', 'old-content'),
            'metadata' => [],
            'status' => 'completed',
            'executed_at' => $oldExecutedAt,
        ]);

        $latest = TranslationMigration::create([
            'filename' => 'latest.json',
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v1.0.0',
            'checksum' => hash('sha256', 'latest-content'),
            'metadata' => [],
            'status' => 'completed',
            'executed_at' => $latestExecutedAt,
        ]);

        TranslationMigration::create([
            'filename' => 'pending.json',
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v1.1.0',
            'checksum' => hash('sha256', 'pending-content'),
            'metadata' => [],
            'status' => 'pending',
        ]);

        // Act
        $latestCompleted = TranslationMigration::latestCompletedForInterface(OrigineInterfaces::WEB_FINANCER);

        // Assert
        $this->assertNotNull($latestCompleted);
        $this->assertEquals($latest->filename, $latestCompleted->filename);
        $this->assertEquals('v1.0.0', $latestCompleted->version);
        $this->assertEquals('completed', $latestCompleted->status);
        $this->assertEquals(OrigineInterfaces::WEB_FINANCER, $latestCompleted->interface_origin);
    }

    #[Test]
    public function it_tracks_batch_numbers(): void
    {
        // Arrange & Act
        $initialBatch1Count = TranslationMigration::where('batch_number', 1)->count();
        $initialBatch2Count = TranslationMigration::where('batch_number', 2)->count();

        $migration1 = TranslationMigration::create([
            'filename' => 'batch_1.json',
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v1.0.0',
            'checksum' => hash('sha256', 'content-1'),
            'metadata' => [],
            'status' => 'completed',
            'batch_number' => 1,
            'executed_at' => now(),
        ]);

        $migration2 = TranslationMigration::create([
            'filename' => 'batch_1_2.json',
            'interface_origin' => OrigineInterfaces::WEB_BENEFICIARY,
            'version' => 'v1.0.0',
            'checksum' => hash('sha256', 'content-2'),
            'metadata' => [],
            'status' => 'completed',
            'batch_number' => 1,
            'executed_at' => now(),
        ]);

        $migration3 = TranslationMigration::create([
            'filename' => 'batch_2.json',
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v1.0.1',
            'checksum' => hash('sha256', 'content-3'),
            'metadata' => [],
            'status' => 'completed',
            'batch_number' => 2,
            'executed_at' => now()->addMinutes(5),
        ]);

        // Assert
        $batch1 = TranslationMigration::where('batch_number', 1)->get();
        $batch2 = TranslationMigration::where('batch_number', 2)->get();

        $this->assertCount($initialBatch1Count + 2, $batch1);
        $this->assertCount($initialBatch2Count + 1, $batch2);
        $this->assertTrue($batch1->contains($migration1));
        $this->assertTrue($batch1->contains($migration2));
        $this->assertTrue($batch2->contains($migration3));
    }

    #[Test]
    public function it_casts_metadata_to_array(): void
    {
        // Arrange
        $metadata = [
            'user_id' => 123,
            'environment' => 'production',
            'keys_count' => 500,
            'locales' => ['fr', 'en', 'es', 'de'],
        ];

        // Act
        $migration = TranslationMigration::create([
            'filename' => 'test.json',
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v1.0.0',
            'checksum' => hash('sha256', 'test-content'),
            'metadata' => $metadata,
            'status' => 'pending',
        ]);

        // Reload from database
        $migration->refresh();

        // Assert
        $this->assertIsArray($migration->metadata);
        $this->assertEquals($metadata, $migration->metadata);
        $this->assertEquals(123, $migration->metadata['user_id']);
        $this->assertCount(4, $migration->metadata['locales']);
    }

    #[Test]
    public function it_casts_dates_correctly(): void
    {
        // Arrange & Act
        $executedAt = now()->subHours(2);
        $rolledBackAt = now()->subHour();

        $migration = TranslationMigration::create([
            'filename' => 'test.json',
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v1.0.0',
            'checksum' => hash('sha256', 'test-content'),
            'metadata' => [],
            'status' => 'rolled_back',
            'executed_at' => $executedAt,
            'rolled_back_at' => $rolledBackAt,
        ]);

        // Reload from database
        $migration->refresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $migration->executed_at);
        $this->assertInstanceOf(Carbon::class, $migration->rolled_back_at);
        $this->assertEquals($executedAt->format('Y-m-d H:i:s'), $migration->executed_at->format('Y-m-d H:i:s'));
        $this->assertEquals($rolledBackAt->format('Y-m-d H:i:s'), $migration->rolled_back_at->format('Y-m-d H:i:s'));
    }
}
