<?php

declare(strict_types=1);

namespace App\Jobs\TranslationMigrations;

use App\Actions\TranslationMigrations\ApplyTranslationMigrationAction;
use App\DTOs\TranslationMigrations\ApplyMigrationDTO;
use App\Models\TranslationMigration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessTranslationMigrationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60; // seconds

    public function __construct(
        public readonly int $migrationId,
        public readonly bool $createBackup = true,
        public readonly bool $validateChecksum = true,
    ) {
        // Use dynamic queue based on active connection
        $queueConnection = config('queue.default');
        $queueName = config("queue.connections.{$queueConnection}.queue", 'default');
        $this->queue = is_string($queueName) ? $queueName : 'default';
    }

    public function uniqueId(): string
    {
        return "migration_{$this->migrationId}";
    }

    public function handle(ApplyTranslationMigrationAction $action): void
    {
        Log::info('ProcessTranslationMigrationJob started', [
            'migration_id' => $this->migrationId,
            'create_backup' => $this->createBackup,
            'validate_checksum' => $this->validateChecksum,
            'queue' => $this->queue,
            'attempts' => $this->attempts(),
            'job_id' => property_exists($this, 'job') && $this->job ? $this->job->getJobId() : 'direct',
        ]);

        try {
            // Get the migration to log its details
            $migration = TranslationMigration::find($this->migrationId);

            if (! $migration) {
                Log::error('Migration not found in database', [
                    'migration_id' => $this->migrationId,
                ]);
                throw new RuntimeException("Migration {$this->migrationId} not found");
            }

            Log::info('Migration details before processing', [
                'migration_id' => $this->migrationId,
                'filename' => $migration->filename,
                'interface' => $migration->interface_origin,
                'status' => $migration->status,
                'current_metadata' => $migration->metadata,
            ]);

            $dto = new ApplyMigrationDTO(
                migrationId: $this->migrationId,
                createBackup: $this->createBackup,
                validateChecksum: $this->validateChecksum,
            );

            Log::info('Calling ApplyTranslationMigrationAction', [
                'migration_id' => $this->migrationId,
                'dto' => [
                    'migrationId' => $dto->migrationId,
                    'createBackup' => $dto->createBackup,
                    'validateChecksum' => $dto->validateChecksum,
                ],
            ]);

            $result = $action->execute($dto);

            Log::info('ApplyTranslationMigrationAction completed', [
                'migration_id' => $this->migrationId,
                'result_success' => $result->success,
                'result_error' => $result->error ?? null,
                'result_backup_path' => $result->backupPath ?? null,
            ]);

            // Reload migration to check final status
            $migration->refresh();
            Log::info('Migration status after action execution', [
                'migration_id' => $this->migrationId,
                'final_status' => $migration->status,
                'final_metadata' => $migration->metadata,
            ]);

            if ($result->success) {
                Log::info('Translation migration processed successfully', [
                    'migration_id' => $this->migrationId,
                    'backup_path' => $result->backupPath,
                    'translations_affected' => $result->translationsAffected ?? null,
                    'final_migration_status' => $migration->status,
                ]);
            } else {
                Log::error('Translation migration processing failed', [
                    'migration_id' => $this->migrationId,
                    'error' => $result->error,
                    'metadata' => $result->metadata,
                    'final_migration_status' => $migration->status,
                ]);

                // Build detailed error message with context
                $errorContext = $result->metadata === []
                    ? 'No additional context'
                    : json_encode($result->metadata, JSON_UNESCAPED_SLASHES);

                throw new RuntimeException(sprintf(
                    'Migration %d failed: %s | Context: %s',
                    $this->migrationId,
                    $result->error ?? 'Unknown error',
                    $errorContext
                ));
            }
        } catch (Throwable $e) {
            Log::error('ProcessTranslationMigrationJob exception caught', [
                'migration_id' => $this->migrationId,
                'exception_class' => get_class($e),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger the failed() method
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Translation migration job failed after retries', [
            'migration_id' => $this->migrationId,
            'exception' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Mark migration as failed in database
        $migration = TranslationMigration::find($this->migrationId);
        if ($migration) {
            $migration->markAsFailed();
            $migration->update([
                'metadata' => array_merge($migration->metadata ?? [], [
                    'job_error' => $exception->getMessage(),
                    'failed_attempts' => $this->attempts(),
                    'job_failed_at' => now()->toIso8601String(),
                ]),
            ]);
        }
    }
}
