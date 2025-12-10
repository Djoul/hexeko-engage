<?php

declare(strict_types=1);

namespace App\Actions\Translation;

use App\DTOs\Translation\RollbackResultDTO;
use App\Services\TranslationMigrations\S3StorageService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RollbackTranslationAction
{
    public function __construct(
        private readonly S3StorageService $s3Service
    ) {}

    /**
     * Execute translation rollback.
     */
    public function execute(array $options = []): RollbackResultDTO
    {
        $interface = $options['interface'];
        $version = $options['version'] ?? null;
        $useLatest = $options['useLatest'] ?? false;

        Log::info('Starting translation rollback', [
            'interface' => $interface,
            'version' => $version,
            'use_latest' => $useLatest,
        ]);

        try {
            return DB::transaction(function () use ($interface, $version, $useLatest): RollbackResultDTO {
                // Get current translation state for backup
                $currentTranslations = $this->getCurrentTranslations($interface);
                $currentVersion = $this->getCurrentVersion();

                // Create backup of current state before rollback
                $backupPath = $this->s3Service->createBackup(
                    $interface,
                    $currentVersion,
                    json_encode($currentTranslations, JSON_THROW_ON_ERROR)
                );

                // Determine rollback version
                if ($useLatest) {
                    $backupContent = $this->s3Service->getLatestBackup($interface);
                    if (in_array($backupContent, [null, '', '0'], true)) {
                        throw new RuntimeException('No backups available for rollback');
                    }
                    $rollbackData = json_decode($backupContent, true, 512, JSON_THROW_ON_ERROR);
                    $version = $rollbackData['version'] ?? 'unknown';
                } else {
                    // Find backup with specific version
                    $backupFiles = $this->s3Service->listBackupFiles($interface);
                    $targetBackup = null;

                    foreach ($backupFiles as $file) {
                        if (str_contains($file, "backup_{$version}_")) {
                            $targetBackup = $file;
                            break;
                        }
                    }

                    if (! $targetBackup) {
                        throw new RuntimeException("No backup found for version: {$version}");
                    }

                    $backupContent = $this->s3Service->downloadMigrationFile($interface, basename($targetBackup));
                    $rollbackData = json_decode($backupContent, true, 512, JSON_THROW_ON_ERROR);
                }

                // Apply rollback
                $filesAffected = $this->applyRollback($interface, $rollbackData);

                Log::info('Translation rollback completed', [
                    'interface' => $interface,
                    'rolled_back_to' => $version,
                    'previous_backup' => $backupPath,
                    'files_affected' => $filesAffected,
                ]);

                return new RollbackResultDTO(
                    success: true,
                    interface: $interface,
                    restoredVersion: $version,
                    previousVersion: $currentVersion,
                    backupPath: $backupPath,
                    filesAffected: $filesAffected,
                    rolledBackAt: Carbon::now()
                );
            });
        } catch (Exception $e) {
            Log::error('Translation rollback failed', [
                'interface' => $interface,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new RollbackResultDTO(
                success: false,
                interface: $interface,
                restoredVersion: '',
                previousVersion: '',
                backupPath: '',
                filesAffected: 0,
                rolledBackAt: Carbon::now(),
                error: $e->getMessage()
            );
        }
    }

    /**
     * Get current translations for backup.
     */
    private function getCurrentTranslations(string $interface): array
    {
        // This would fetch current translations from your storage
        // Implementation depends on your translation storage mechanism
        return [
            'version' => $this->getCurrentVersion(),
            'translations' => [
                // Language => keys mapping
            ],
            'metadata' => [
                'backed_up_at' => Carbon::now()->toIso8601String(),
                'interface' => $interface,
            ],
        ];
    }

    /**
     * Get current version of translations.
     */
    private function getCurrentVersion(): string
    {
        // This would determine the current version
        // Could be based on latest migration or timestamp
        return Carbon::now()->format('Y-m-d_His');
    }

    /**
     * Apply rollback to translation files.
     */
    private function applyRollback(string $interface, array $rollbackData): int
    {
        $filesAffected = 0;

        // This would apply the rollback to your translation storage
        // Implementation depends on your translation storage mechanism
        foreach ($rollbackData['translations'] ?? [] as $language => $keys) {
            // Apply translations for each language
            $filesAffected++;

            Log::info("Applying rollback for {$language}", [
                'interface' => $interface,
                'keys_count' => count($keys),
            ]);
        }

        return $filesAffected;
    }
}
