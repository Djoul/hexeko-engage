<?php

declare(strict_types=1);

namespace App\Jobs\TranslationMigrations;

use App\Models\TranslationMigration;
use App\Services\EnvironmentService;
use App\Services\TranslationManifestService;
use App\Services\TranslationMigrations\TranslationMigrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class AutoProcessTranslationMigrationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly string $interface
    ) {
        // Use dynamic queue based on active connection
        $queueConnection = config('queue.default');
        $queueName = config("queue.connections.{$queueConnection}.queue", 'default');
        $this->queue = is_string($queueName) ? $queueName : 'default';
    }

    public function uniqueId(): string
    {
        return "auto_translation_migration_{$this->interface}";
    }

    public function handle(): void
    {
        Log::info('Auto translation migration sync started', [
            'interface' => $this->interface,
            'environment' => app()->environment(),
        ]);

        $migrationService = app(TranslationMigrationService::class);

        try {
            // Phase 1: Sync new files from S3 using service
            $syncedCount = $migrationService->syncMigrationsFromS3($this->interface, [
                'auto_process' => true,
                'automation_trigger' => 'migrations_ended',
                'environment' => app()->environment(),
            ]);

            // Phase 2: Dispatch apply jobs for all pending migrations
            $appliedCount = $this->dispatchApplyJobs();

            Log::info('Auto translation migration sync completed', [
                'interface' => $this->interface,
                'files_synced' => $syncedCount,
                'apply_jobs_dispatched' => $appliedCount,
            ]);

        } catch (Throwable $exception) {
            Log::error('Auto translation migration sync failed', [
                'interface' => $this->interface,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Auto translation migration processing failed', [
            'interface' => $this->interface,
            'job' => self::class,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    private function dispatchApplyJobs(): int
    {
        $pendingMigrations = TranslationMigration::pending()
            ->where('interface_origin', $this->interface)
            ->orderBy('created_at')
            ->get();

        $environmentService = app(EnvironmentService::class);
        $manifestService = app(TranslationManifestService::class);
        $dispatchedCount = 0;

        foreach ($pendingMigrations as $migration) {
            // Check manifest validation for staging/production
            if ($environmentService->requiresManifest() && ! $manifestService->validateAgainstManifest($this->interface, $migration->filename)) {
                Log::warning('Migration skipped - not in approved manifest', [
                    'migration_id' => $migration->id,
                    'filename' => $migration->filename,
                    'interface' => $migration->interface_origin,
                    'environment' => app()->environment(),
                ]);

                continue;
            }

            // Get dynamic queue name based on active connection
            $queueConnection = config('queue.default');
            $queueName = config("queue.connections.{$queueConnection}.queue", 'default');
            $queueNameStr = is_string($queueName) ? $queueName : 'default';

            ProcessTranslationMigrationJob::dispatch(
                migrationId: $migration->id,
                createBackup: true,
                validateChecksum: true
            )->onQueue($queueNameStr);

            Log::info('Apply job dispatched for migration', [
                'migration_id' => $migration->id,
                'filename' => $migration->filename,
                'interface' => $migration->interface_origin,
                'manifest_validated' => $environmentService->requiresManifest(),
            ]);

            $dispatchedCount++;
        }

        if ($dispatchedCount < $pendingMigrations->count()) {
            Log::info('Some migrations were skipped due to manifest validation', [
                'interface' => $this->interface,
                'total_pending' => $pendingMigrations->count(),
                'dispatched' => $dispatchedCount,
                'skipped' => $pendingMigrations->count() - $dispatchedCount,
            ]);
        }

        return $dispatchedCount;
    }
}
