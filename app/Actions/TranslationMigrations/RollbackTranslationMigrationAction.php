<?php

declare(strict_types=1);

namespace App\Actions\TranslationMigrations;

use App\DTOs\TranslationMigrations\MigrationResultDTO;
use App\DTOs\TranslationMigrations\RollbackMigrationDTO;
use App\Events\TranslationMigrationRolledBack;
use App\Models\TranslationMigration;
use App\Services\TranslationMigrations\TranslationMigrationService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RollbackTranslationMigrationAction
{
    public function __construct(
        private readonly TranslationMigrationService $migrationService,
    ) {}

    public function execute(RollbackMigrationDTO $dto): MigrationResultDTO
    {
        $migration = TranslationMigration::findOrFail($dto->migrationId);

        DB::beginTransaction();

        try {
            // Validate migration can be rolled back
            if ($migration->status !== 'completed') {
                if ($migration->status === 'rolled_back') {
                    $error = 'Migration has already been rolled back';
                } else {
                    $error = 'Only completed migrations can be rolled back';
                }

                DB::rollBack();

                Log::warning('Rollback attempt failed', [
                    'migration_id' => $migration->id,
                    'status' => $migration->status,
                    'error' => $error,
                ]);

                return MigrationResultDTO::failure(
                    migrationId: $migration->id,
                    error: $error
                );
            }

            // Check if backup exists
            $backupPath = $migration->metadata['backup_path'] ?? null;
            if (! $backupPath) {
                $error = 'No backup found for this migration';

                DB::rollBack();

                Log::error('Rollback failed - no backup', [
                    'migration_id' => $migration->id,
                ]);

                return MigrationResultDTO::failure(
                    migrationId: $migration->id,
                    error: $error
                );
            }

            // Update migration metadata with rollback reason
            $migration->update([
                'metadata' => array_merge($migration->metadata ?? [], [
                    'rollback_reason' => $dto->reason,
                    'rollback_requested_at' => now()->toIso8601String(),
                ]),
            ]);

            // Perform the rollback
            $success = $this->migrationService->rollbackMigration($migration);

            if (! $success) {
                DB::rollBack();

                $error = 'Rollback failed during execution';

                Log::error('Rollback execution failed', [
                    'migration_id' => $migration->id,
                    'filename' => $migration->filename,
                ]);

                return MigrationResultDTO::failure(
                    migrationId: $migration->id,
                    error: $error
                );
            }

            DB::commit();

            // Dispatch success event
            event(new TranslationMigrationRolledBack($migration, $dto->reason));

            Log::info('Translation migration rolled back successfully', [
                'migration_id' => $migration->id,
                'filename' => $migration->filename,
                'reason' => $dto->reason,
                'backup_path' => $backupPath,
            ]);

            return MigrationResultDTO::success(
                migrationId: $migration->id,
                backupPath: $backupPath,
                metadata: [
                    'interface' => $migration->interface_origin,
                    'version' => $migration->version,
                    'rollback_reason' => $dto->reason,
                ]
            );

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Rollback action failed with exception', [
                'migration_id' => $migration->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
