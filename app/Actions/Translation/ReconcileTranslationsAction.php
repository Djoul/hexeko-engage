<?php

declare(strict_types=1);

namespace App\Actions\Translation;

use App\DTOs\Translation\ReconciliationResultDTO;
use App\Jobs\TranslationMigrations\ProcessTranslationMigrationJob;
use App\Models\TranslationMigration;
use App\Services\TranslationMigrations\TranslationMigrationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReconcileTranslationsAction
{
    private const RECONCILIATION_INTERVAL = 5; // minutes

    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private readonly TranslationMigrationService $migrationService
    ) {}

    /**
     * Execute translation reconciliation for specified interfaces.
     */
    public function execute(array $options = []): ReconciliationResultDTO
    {
        $runId = Str::uuid()->toString();
        $startedAt = now();
        $interfaces = $options['interfaces'] ?? ['mobile', 'web_financer', 'web_beneficiary'];
        $force = $options['force'] ?? false;

        Log::info('Starting translation reconciliation', [
            'run_id' => $runId,
            'interfaces' => $interfaces,
            'force' => $force,
        ]);

        $totalFilesSynced = 0;
        $totalJobsDispatched = 0;
        $interfaceResults = [];

        foreach ($interfaces as $interface) {
            // Skip if recently reconciled (unless forced)
            if (! $force && $this->wasRecentlyReconciled($interface)) {
                Log::info("Skipping {$interface} - recently reconciled");

                continue;
            }

            $result = $this->reconcileInterface($interface, $runId);
            $totalFilesSynced += $result['files_synced'];
            $totalJobsDispatched += $result['jobs_dispatched'];
            $interfaceResults[$interface] = $result;

            // Mark as reconciled
            $this->markAsReconciled($interface);
        }

        $completedAt = now();

        Log::info('Translation reconciliation completed', [
            'run_id' => $runId,
            'duration' => $completedAt->diffInSeconds($startedAt),
            'files_synced' => $totalFilesSynced,
            'jobs_dispatched' => $totalJobsDispatched,
        ]);

        return new ReconciliationResultDTO(
            runId: $runId,
            startedAt: $startedAt,
            completedAt: $completedAt,
            interfaces: $interfaceResults,
            totalFilesSynced: $totalFilesSynced,
            totalJobsDispatched: $totalJobsDispatched,
            success: true,
            error: null
        );
    }

    /**
     * Reconcile a single interface.
     */
    private function reconcileInterface(string $interface, string $runId): array
    {
        // Sync new files from S3
        $syncedCount = $this->migrationService->syncMigrationsFromS3($interface, [
            'reconciliation_run' => $runId,
            'trigger' => 'reconciliation',
        ]);

        // Dispatch jobs for pending migrations
        $jobsDispatched = $this->dispatchPendingJobs($interface);

        return [
            'interface' => $interface,
            'files_synced' => $syncedCount,
            'jobs_dispatched' => $jobsDispatched,
            'reconciled_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Dispatch processing jobs for pending migrations.
     */
    private function dispatchPendingJobs(string $interface): int
    {
        $pendingMigrations = TranslationMigration::pending()
            ->where('interface_origin', $interface)
            ->orderBy('created_at')
            ->get();

        // Get dynamic queue name based on active connection
        $queueConnection = config('queue.default');
        $queueName = config("queue.connections.{$queueConnection}.queue", 'default');
        $queueNameStr = is_string($queueName) ? $queueName : 'default';

        foreach ($pendingMigrations as $migration) {
            ProcessTranslationMigrationJob::dispatch(
                migrationId: $migration->id,
                createBackup: true,
                validateChecksum: true
            )->onQueue($queueNameStr);

            Log::info('Dispatched processing job for migration', [
                'migration_id' => $migration->id,
                'filename' => $migration->filename,
                'interface' => $interface,
            ]);
        }

        return $pendingMigrations->count();
    }

    /**
     * Check if interface was recently reconciled.
     */
    private function wasRecentlyReconciled(string $interface): bool
    {
        $lastReconciliation = Cache::get("last_reconciliation_{$interface}");

        if (! $lastReconciliation) {
            return false;
        }

        $lastTime = Carbon::parse($lastReconciliation);

        return $lastTime->diffInMinutes(now()) < self::RECONCILIATION_INTERVAL;
    }

    /**
     * Mark interface as reconciled.
     */
    private function markAsReconciled(string $interface): void
    {
        Cache::put(
            "last_reconciliation_{$interface}",
            now()->toIso8601String(),
            self::CACHE_TTL
        );
    }
}
