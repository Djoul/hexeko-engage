<?php

declare(strict_types=1);

namespace App\Jobs\TranslationMigrations;

use App\Services\TranslationMigrations\TranslationMigrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTranslationMigrationsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60; // seconds

    public function __construct(
        public readonly string $interface,
        public readonly bool $autoProcess = false,
    ) {
        // Use dynamic queue based on active connection
        $queueConnection = config('queue.default');
        $queueName = config("queue.connections.{$queueConnection}.queue", 'default');
        $this->queue = is_string($queueName) ? $queueName : 'default';
    }

    public function uniqueId(): string
    {
        return "sync_{$this->interface}";
    }

    public function handle(TranslationMigrationService $migrationService): void
    {
        Log::info('Syncing translation migrations from S3', [
            'interface' => $this->interface,
            'auto_process' => $this->autoProcess,
        ]);

        // Sync migrations from S3
        $syncedCount = $migrationService->syncMigrationsFromS3($this->interface);

        Log::info('Translation migrations synced', [
            'interface' => $this->interface,
            'synced_count' => $syncedCount,
        ]);

        // Auto-process pending migrations if enabled
        if ($this->autoProcess && $syncedCount > 0) {
            $this->processPendingMigrations($migrationService);
        }
    }

    private function processPendingMigrations(TranslationMigrationService $migrationService): void
    {
        $pendingMigrations = $migrationService->getPendingMigrations();

        // Filter for this interface only
        $interfaceMigrations = $pendingMigrations->filter(
            fn ($migration): bool => $migration->interface_origin === $this->interface
        );

        Log::info('Dispatching processing jobs for pending migrations', [
            'interface' => $this->interface,
            'pending_count' => $interfaceMigrations->count(),
        ]);

        foreach ($interfaceMigrations as $migration) {
            ProcessTranslationMigrationJob::dispatch(
                migrationId: $migration->id,
                createBackup: true,
                validateChecksum: true
            );
        }
    }
}
