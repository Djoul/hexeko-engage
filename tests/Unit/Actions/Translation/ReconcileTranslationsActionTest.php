<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Translation;

use App\Actions\Translation\ReconcileTranslationsAction;
use App\DTOs\Translation\ReconciliationResultDTO;
use App\Jobs\TranslationMigrations\ProcessTranslationMigrationJob;
use App\Models\TranslationMigration;
use App\Services\TranslationMigrations\S3StorageService;
use App\Services\TranslationMigrations\TranslationMigrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('admin-panel')]
#[Group('translation')]
#[Group('translation-migration')]
#[Group('translation-protection')]
class ReconcileTranslationsActionTest extends TestCase
{
    use DatabaseTransactions;

    private ReconcileTranslationsAction $action;

    private MockInterface $migrationService;

    private MockInterface $s3Service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrationService = $this->mock(TranslationMigrationService::class);
        $this->s3Service = $this->mock(S3StorageService::class);

        $this->action = new ReconcileTranslationsAction(
            $this->migrationService,
            $this->s3Service
        );

        Queue::fake();
        Cache::flush();

        // Clear any existing migrations to ensure test isolation
        TranslationMigration::query()->delete();
    }

    #[Test]
    public function it_processes_all_three_interfaces(): void
    {
        // Arrange
        $interfaces = ['mobile', 'web_financer', 'web_beneficiary'];
        $migrations = [];

        foreach ($interfaces as $interface) {
            $this->migrationService->shouldReceive('syncMigrationsFromS3')
                ->withArgs(function ($arg1, $arg2) use ($interface): bool {
                    return $arg1 === $interface &&
                           is_array($arg2) &&
                           isset($arg2['reconciliation_run']) &&
                           isset($arg2['trigger']) &&
                           $arg2['trigger'] === 'reconciliation';
                })
                ->once()
                ->andReturn(2);

            // Create pending migrations for each interface
            $migrations[] = TranslationMigration::factory()->create([
                'interface_origin' => $interface,
                'status' => 'pending',
                'filename' => "{$interface}_migration_test.json",
            ]);
        }

        // Act
        $result = $this->action->execute();

        // Assert
        $this->assertInstanceOf(ReconciliationResultDTO::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals(6, $result->totalFilesSynced); // 2 files x 3 interfaces
        $this->assertEquals(3, $result->totalJobsDispatched); // 1 per interface

        // Check that jobs were dispatched for the created migrations
        foreach ($migrations as $migration) {
            Queue::assertPushed(ProcessTranslationMigrationJob::class, function ($job) use ($migration): bool {
                return $job->migrationId === $migration->id;
            });
        }
    }

    #[Test]
    public function it_skips_interface_with_recent_reconciliation(): void
    {
        // Arrange
        Cache::put('last_reconciliation_mobile', now()->subMinutes(2)->toIso8601String());

        $this->migrationService->shouldReceive('syncMigrationsFromS3')
            ->withArgs(function ($arg1, $arg2): bool {
                return $arg1 === 'web_financer' &&
                       is_array($arg2) &&
                       isset($arg2['reconciliation_run']) &&
                       isset($arg2['trigger']) &&
                       $arg2['trigger'] === 'reconciliation';
            })
            ->once()
            ->andReturn(1);

        $this->migrationService->shouldReceive('syncMigrationsFromS3')
            ->withArgs(function ($arg1, $arg2): bool {
                return $arg1 === 'web_beneficiary' &&
                       is_array($arg2) &&
                       isset($arg2['reconciliation_run']) &&
                       isset($arg2['trigger']) &&
                       $arg2['trigger'] === 'reconciliation';
            })
            ->once()
            ->andReturn(1);

        // Create pending migrations only for the interfaces that will be processed
        $migration1 = TranslationMigration::factory()->create([
            'interface_origin' => 'web_financer',
            'status' => 'pending',
            'filename' => 'web_financer_test.json',
        ]);

        $migration2 = TranslationMigration::factory()->create([
            'interface_origin' => 'web_beneficiary',
            'status' => 'pending',
            'filename' => 'web_beneficiary_test.json',
        ]);

        // Act
        $result = $this->action->execute();

        // Assert
        $this->assertInstanceOf(ReconciliationResultDTO::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals(2, $result->totalFilesSynced);
        $this->assertEquals(2, $result->totalJobsDispatched);

        Queue::assertPushed(ProcessTranslationMigrationJob::class, function ($job) use ($migration1): bool {
            return $job->migrationId === $migration1->id;
        });
        Queue::assertPushed(ProcessTranslationMigrationJob::class, function ($job) use ($migration2): bool {
            return $job->migrationId === $migration2->id;
        });
    }

    #[Test]
    public function it_creates_missing_migration_records(): void
    {
        // Arrange
        $interface = 'mobile';

        $this->migrationService->shouldReceive('syncMigrationsFromS3')
            ->withArgs(function ($arg1, $arg2) use ($interface): bool {
                return $arg1 === $interface &&
                       is_array($arg2) &&
                       isset($arg2['reconciliation_run']) &&
                       isset($arg2['trigger']) &&
                       $arg2['trigger'] === 'reconciliation';
            })
            ->once()
            ->andReturn(3);

        // Act
        $result = $this->action->execute(['interfaces' => [$interface]]);

        // Assert
        $this->assertInstanceOf(ReconciliationResultDTO::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals(3, $result->totalFilesSynced);
    }

    #[Test]
    public function it_dispatches_jobs_for_pending_migrations(): void
    {
        // Arrange
        $interface = 'mobile';

        $this->migrationService->shouldReceive('syncMigrationsFromS3')
            ->withArgs(function ($arg1, $arg2) use ($interface): bool {
                return $arg1 === $interface &&
                       is_array($arg2) &&
                       isset($arg2['reconciliation_run']) &&
                       isset($arg2['trigger']) &&
                       $arg2['trigger'] === 'reconciliation';
            })
            ->once()
            ->andReturn(0);

        // Create pending migrations
        $migration1 = TranslationMigration::factory()->create([
            'interface_origin' => $interface,
            'status' => 'pending',
            'filename' => 'mobile_001.json',
        ]);

        $migration2 = TranslationMigration::factory()->create([
            'interface_origin' => $interface,
            'status' => 'pending',
            'filename' => 'mobile_002.json',
        ]);

        // Act
        $result = $this->action->execute(['interfaces' => [$interface]]);

        // Assert
        $this->assertEquals(2, $result->totalJobsDispatched);

        Queue::assertPushed(ProcessTranslationMigrationJob::class, function ($job) use ($migration1): bool {
            return $job->migrationId === $migration1->id;
        });

        Queue::assertPushed(ProcessTranslationMigrationJob::class, function ($job) use ($migration2): bool {
            return $job->migrationId === $migration2->id;
        });
    }

    #[Test]
    public function it_returns_reconciliation_result_dto(): void
    {
        // Arrange
        $this->migrationService->shouldReceive('syncMigrationsFromS3')
            ->withArgs(function ($arg1, $arg2): bool {
                return in_array($arg1, ['mobile', 'web_financer', 'web_beneficiary']) &&
                       is_array($arg2) &&
                       isset($arg2['reconciliation_run']) &&
                       isset($arg2['trigger']) &&
                       $arg2['trigger'] === 'reconciliation';
            })
            ->times(3)
            ->andReturn(1);

        // Act
        $result = $this->action->execute();

        // Assert
        $this->assertInstanceOf(ReconciliationResultDTO::class, $result);
        $this->assertTrue($result->success);
        $this->assertNotNull($result->runId);
        $this->assertNotNull($result->startedAt);
        $this->assertNotNull($result->completedAt);
        $this->assertIsArray($result->interfaces);
        $this->assertEquals(3, $result->totalFilesSynced);
    }

    #[Test]
    public function it_respects_force_flag_to_bypass_time_check(): void
    {
        // Arrange
        // Set recent reconciliation for all interfaces
        foreach (['mobile', 'web_financer', 'web_beneficiary'] as $interface) {
            Cache::put("last_reconciliation_{$interface}", now()->subMinutes(2)->toIso8601String());

            $this->migrationService->shouldReceive('syncMigrationsFromS3')
                ->withArgs(function ($arg1, $arg2) use ($interface): bool {
                    return $arg1 === $interface &&
                           is_array($arg2) &&
                           isset($arg2['reconciliation_run']) &&
                           isset($arg2['trigger']) &&
                           $arg2['trigger'] === 'reconciliation';
                })
                ->once()
                ->andReturn(1);
        }

        // Act with force flag
        $result = $this->action->execute(['force' => true]);

        // Assert
        $this->assertInstanceOf(ReconciliationResultDTO::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals(3, $result->totalFilesSynced);
    }
}
