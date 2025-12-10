<?php

declare(strict_types=1);

namespace App\Services\TranslationMigrations;

use App\Actions\Translation\ExportTranslationsAction;
use App\Actions\Translation\ImportTranslationsAction;
use App\DTOs\Translation\ImportTranslationDTO;
use App\Models\TranslationMigration;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TranslationMigrationService
{
    public function __construct(
        private readonly S3StorageService $s3StorageService,
        private readonly ImportTranslationsAction $importTranslationsAction
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function trackMigration(
        string $filename,
        string $interface,
        string $version,
        string $checksum,
        array $metadata = []
    ): TranslationMigration {
        // Verify file exists in S3
        if (! $this->s3StorageService->migrationFileExists($interface, $filename)) {
            throw new RuntimeException("Migration file does not exist in S3: {$filename}");
        }

        return TranslationMigration::create([
            'filename' => $filename,
            'interface_origin' => $interface,
            'version' => $version,
            'checksum' => $checksum,
            'metadata' => $metadata,
            'status' => 'pending',
        ]);
    }

    public function applyMigration(TranslationMigration $migration): bool
    {
        Log::info('TranslationMigrationService::applyMigration started', [
            'migration_id' => $migration->id,
            'filename' => $migration->filename,
            'interface' => $migration->interface_origin,
            'initial_status' => $migration->status,
        ]);

        DB::beginTransaction();

        try {
            // Mark as processing
            Log::info('Marking migration as processing', [
                'migration_id' => $migration->id,
            ]);

            $migration->markAsProcessing();

            Log::info('Migration marked as processing', [
                'migration_id' => $migration->id,
                'status_after_mark' => $migration->status,
            ]);

            // Download migration file from S3
            Log::info('Downloading migration file from S3', [
                'migration_id' => $migration->id,
                'interface' => $migration->interface_origin,
                'filename' => $migration->filename,
            ]);

            $content = $this->s3StorageService->downloadMigrationFile(
                $migration->interface_origin,
                $migration->filename
            );

            Log::info('Migration file downloaded', [
                'migration_id' => $migration->id,
                'content_length' => strlen($content),
            ]);

            $dto = ImportTranslationDTO::fromFileUpload(
                $content,
                $migration->filename,
                $migration->interface_origin,
                'multilingual'
            );

            Log::info('ImportTranslationDTO created', [
                'migration_id' => $migration->id,
                'translations_count' => count($dto->translations ?? []),
            ]);

            if ($dto->translations === []) {
                throw new RuntimeException('Migration file does not contain translations');
            }

            Log::info('Executing import translations action', [
                'migration_id' => $migration->id,
                'interface' => $migration->interface_origin,
                'translations_count' => count($dto->translations),
            ]);

            $importResult = $this->importTranslationsAction->execute([
                'interface' => $migration->interface_origin,
                'translations' => $dto->translations,
                'update_existing_values' => true,
            ], $migration->interface_origin, false);

            Log::info('Import translations action completed', [
                'migration_id' => $migration->id,
                'import_success' => $importResult['success'] ?? false,
                'import_summary' => $importResult['summary'] ?? [],
            ]);

            if (! ($importResult['success'] ?? false)) {
                throw new RuntimeException('Translations import failed during migration application');
            }

            // Get next batch number if not set
            if (! $migration->batch_number) {
                $migration->batch_number = $this->getLatestBatchNumber() + 1;
                $migration->save();
                Log::info('Batch number assigned', [
                    'migration_id' => $migration->id,
                    'batch_number' => $migration->batch_number,
                ]);
            }

            // Mark as completed
            Log::info('Marking migration as completed', [
                'migration_id' => $migration->id,
            ]);

            $migration->markAsCompleted();

            Log::info('Migration marked as completed', [
                'migration_id' => $migration->id,
                'status_after_mark' => $migration->status,
            ]);

            $migration->update([
                'metadata' => array_merge($migration->metadata ?? [], [
                    'summary' => $importResult['summary'] ?? [],
                    'applied_at' => Date::now()->toIso8601String(),
                ]),
            ]);

            Log::info('Migration metadata updated', [
                'migration_id' => $migration->id,
                'final_status' => $migration->status,
                'metadata' => $migration->metadata,
            ]);

            DB::commit();

            Log::info('Transaction committed, migration applied successfully', [
                'migration_id' => $migration->id,
                'final_status' => $migration->status,
            ]);

            return true;

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Exception in applyMigration, transaction rolled back', [
                'migration_id' => $migration->id,
                'exception' => get_class($e),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Mark as failed and store error (outside of the rolled back transaction)
            DB::transaction(function () use ($migration, $e): void {
                Log::info('Starting separate transaction to mark as failed', [
                    'migration_id' => $migration->id,
                ]);

                $migration->markAsFailed();

                Log::info('Migration marked as failed', [
                    'migration_id' => $migration->id,
                    'status_after_mark' => $migration->status,
                ]);

                $migration->update([
                    'metadata' => array_merge($migration->metadata ?? [], [
                        'error' => $e->getMessage(),
                        'failed_at' => Date::now()->toIso8601String(),
                    ]),
                ]);

                Log::info('Failed migration metadata updated', [
                    'migration_id' => $migration->id,
                    'final_status' => $migration->status,
                ]);
            });

            Log::error('Migration failed', [
                'migration' => $migration->filename,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function rollbackMigration(TranslationMigration $migration): bool
    {
        DB::beginTransaction();

        try {
            // Check if backup exists
            $backupPath = $migration->metadata['backup_path'] ?? null;
            if (! $backupPath) {
                throw new RuntimeException('No backup path found for migration');
            }

            // Download backup from S3
            $backupContent = $this->s3StorageService->downloadMigrationFile(
                $migration->interface_origin,
                basename($backupPath)
            );

            // Parse and validate backup
            $backupData = json_decode($backupContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Invalid JSON in backup file');
            }

            // Mark as rolled back
            $migration->markAsRolledBack();

            DB::commit();

            return true;

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Rollback failed', [
                'migration' => $migration->filename,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getPendingMigrations(): Collection
    {
        return TranslationMigration::pending()->get();
    }

    public function getMigrationsForInterface(string $interface): Collection
    {
        return TranslationMigration::forInterface($interface)->get();
    }

    public function migrationExists(string $filename): bool
    {
        return TranslationMigration::where('filename', $filename)->exists();
    }

    public function getLatestBatchNumber(): int
    {
        $maxBatch = TranslationMigration::max('batch_number');

        return is_numeric($maxBatch) ? (int) $maxBatch : 0;
    }

    public function syncMigrationsFromS3(string $interface, array $additionalMetadata = []): int
    {
        $s3Files = $this->s3StorageService->listMigrationFiles($interface);
        $syncedCount = 0;

        foreach ($s3Files as $filePath) {
            $filename = is_string($filePath) ? basename($filePath) : '';

            // Check if migration already tracked
            if ($this->migrationExists($filename)) {
                continue;
            }

            // Download content to generate checksum
            $content = $this->s3StorageService->downloadMigrationFile($interface, $filename);
            $checksum = hash('sha256', $content);

            // Extract version from filename (format: interface_YYYY-MM-DD_HHiiss.json)
            preg_match('/(\d{4}-\d{2}-\d{2}_\d{6})/', $filename, $matches);
            $version = $matches[1] ?? date('Y-m-d_His');

            // Prepare metadata
            $metadata = array_merge([
                's3_path' => $filePath,
                'synced_at' => Date::now()->toIso8601String(),
                'synced_from_s3' => true,
            ], $additionalMetadata);

            // Track new migration
            $this->trackMigration(
                $filename,
                $interface,
                $version,
                $checksum,
                $metadata
            );

            $syncedCount++;

            Log::info('Migration synced from S3', [
                'interface' => $interface,
                'filename' => $filename,
                'version' => $version,
                'checksum' => substr($checksum, 0, 8).'...',
            ]);
        }

        return $syncedCount;
    }

    public function validateMigrationChecksum(TranslationMigration $migration): bool
    {
        $currentChecksum = $this->s3StorageService->getFileChecksum(
            $migration->interface_origin,
            $migration->filename
        );

        return $currentChecksum === $migration->checksum;
    }

    /**
     * @param  Collection<int, TranslationMigration>  $migrations
     * @return Collection<int, bool>
     */
    public function processBatch(Collection $migrations): Collection
    {
        $batchNumber = $this->getLatestBatchNumber() + 1;
        $results = collect();

        foreach ($migrations as $migration) {
            if ($migration instanceof TranslationMigration) {
                // Set batch number
                $migration->batch_number = $batchNumber;
                $migration->save();

                // Apply migration
                $success = $this->applyMigration($migration);
                $results->push($success);
            }
        }

        return $results;
    }

    /**
     * Export current translations for a specific interface as JSON using the same format as ExportTranslationsAction
     */
    public function exportCurrentTranslations(string $interface): string
    {
        $exportAction = app(ExportTranslationsAction::class);
        $exportData = $exportAction->execute($interface);

        return json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
