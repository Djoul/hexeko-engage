<?php

declare(strict_types=1);

namespace App\Actions\TranslationMigrations;

use App\DTOs\TranslationMigrations\ApplyMigrationDTO;
use App\DTOs\TranslationMigrations\MigrationResultDTO;
use App\Events\TranslationMigrationApplied;
use App\Events\TranslationMigrationFailed;
use App\Models\TranslationMigration;
use App\Services\TranslationMigrations\S3StorageService;
use App\Services\TranslationMigrations\TranslationMigrationService;
use Exception;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

class ApplyTranslationMigrationAction
{
    public function __construct(
        private readonly TranslationMigrationService $migrationService,
        private readonly S3StorageService $s3Service,
    ) {}

    public function execute(ApplyMigrationDTO $dto): MigrationResultDTO
    {
        Log::info('ApplyTranslationMigrationAction::execute started', [
            'dto' => [
                'migrationId' => $dto->migrationId,
                'createBackup' => $dto->createBackup,
                'validateChecksum' => $dto->validateChecksum,
            ],
        ]);

        $migration = TranslationMigration::findOrFail($dto->migrationId);

        Log::info('Migration loaded from database', [
            'migration_id' => $migration->id,
            'filename' => $migration->filename,
            'initial_status' => $migration->status,
            'interface' => $migration->interface_origin,
        ]);

        try {
            // Validate checksum if requested
            if ($dto->validateChecksum) {
                Log::info('Validating migration checksum', [
                    'migration_id' => $migration->id,
                ]);

                $checksumValid = $this->migrationService->validateMigrationChecksum($migration);

                Log::info('Checksum validation result', [
                    'migration_id' => $migration->id,
                    'checksum_valid' => $checksumValid,
                ]);

                if (! $checksumValid) {
                    $error = 'Checksum validation failed for migration';
                    $this->logAndDispatchFailure($migration, $error);

                    Log::error('Checksum validation failed, returning failure', [
                        'migration_id' => $migration->id,
                    ]);

                    // Mark as failed
                    $migration->markAsFailed();
                    $migration->update([
                        'metadata' => array_merge($migration->metadata ?? [], [
                            'error' => $error,
                            'failed_at' => Date::now()->toIso8601String(),
                        ]),
                    ]);

                    return MigrationResultDTO::failure(
                        migrationId: $migration->id,
                        error: $error,
                        metadata: [
                            'filename' => $migration->filename,
                            'interface' => $migration->interface_origin,
                            'validation_type' => 'checksum',
                            'checksum_expected' => $migration->checksum,
                        ]
                    );
                }
            }

            // Create backup if requested
            $backupPath = null;
            if ($dto->createBackup) {
                Log::info('Creating backup before migration', [
                    'migration_id' => $migration->id,
                ]);

                $backupPath = $this->createBackup($migration);

                Log::info('Backup created successfully', [
                    'migration_id' => $migration->id,
                    'backup_path' => $backupPath,
                ]);

                // Update migration metadata with backup path
                $migration->update([
                    'metadata' => array_merge($migration->metadata ?? [], [
                        'backup_path' => $backupPath,
                    ]),
                ]);
            }

            Log::info('Calling migrationService->applyMigration', [
                'migration_id' => $migration->id,
            ]);

            // Apply the migration (has its own transaction)
            $success = $this->migrationService->applyMigration($migration);

            Log::info('migrationService->applyMigration completed', [
                'migration_id' => $migration->id,
                'success' => $success,
            ]);

            if (! $success) {
                Log::error('Migration application failed', [
                    'migration_id' => $migration->id,
                ]);

                // The service already marks the migration as failed
                // Reload to get the updated status
                $migration->refresh();

                Log::info('Migration status after service failure', [
                    'migration_id' => $migration->id,
                    'status' => $migration->status,
                ]);

                // Dispatch failure event
                $error = 'Migration application failed';
                event(new TranslationMigrationFailed($migration, $error));

                // Just return failure DTO with metadata
                return MigrationResultDTO::failure(
                    migrationId: $migration->id,
                    error: $error,
                    metadata: [
                        'filename' => $migration->filename,
                        'interface' => $migration->interface_origin,
                        'service_status' => $migration->status,
                        'service_metadata' => $migration->metadata,
                    ]
                );
            }

            Log::info('Migration applied successfully by service', [
                'migration_id' => $migration->id,
            ]);

            // The service already marks the migration as completed
            // Reload to get latest status
            $migration->refresh();

            Log::info('Migration status after refresh', [
                'migration_id' => $migration->id,
                'status' => $migration->status,
                'metadata' => $migration->metadata,
            ]);

            // Dispatch success event
            event(new TranslationMigrationApplied($migration, $backupPath));

            Log::info('Translation migration applied successfully', [
                'migration_id' => $migration->id,
                'filename' => $migration->filename,
                'backup_path' => $backupPath,
                'final_status' => $migration->status,
            ]);

            return MigrationResultDTO::success(
                migrationId: $migration->id,
                backupPath: $backupPath,
                metadata: [
                    'interface' => $migration->interface_origin,
                    'version' => $migration->version,
                ]
            );

        } catch (Exception $e) {
            Log::error('Exception caught in ApplyTranslationMigrationAction', [
                'migration_id' => $migration->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->logAndDispatchFailure($migration, $e->getMessage());

            throw $e;
        }
    }

    private function createBackup(TranslationMigration $migration): string
    {
        try {
            // Download current production translations
            $currentContent = $this->s3Service->downloadMigrationFile(
                $migration->interface_origin,
                'current.json'
            );

            // Ensure we have a valid string
            if (! is_string($currentContent)) {
                $currentContent = '{}';
            }
        } catch (Exception $e) {
            // If current file doesn't exist, create empty backup
            Log::warning('Current translation file not found, creating empty backup', [
                'interface' => $migration->interface_origin,
            ]);
            $encodedContent = json_encode([]);
            $currentContent = is_string($encodedContent) ? $encodedContent : '{}';
        }

        // Create backup in S3 with unified naming
        return $this->s3Service->createUnifiedBackup(
            $migration->interface_origin,
            'before-apply-migration',
            $currentContent,
            'json'
        );
    }

    private function logAndDispatchFailure(TranslationMigration $migration, string $error): void
    {
        Log::error('Translation migration failed', [
            'migration_id' => $migration->id,
            'filename' => $migration->filename,
            'error' => $error,
        ]);

        event(new TranslationMigrationFailed($migration, $error));
    }
}
