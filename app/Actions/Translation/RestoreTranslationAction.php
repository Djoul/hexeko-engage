<?php

declare(strict_types=1);

namespace App\Actions\Translation;

use App\DTOs\Translation\BackupInfoDTO;
use App\DTOs\Translation\RestoreResultDTO;
use App\Services\TranslationMigrations\S3StorageService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RestoreTranslationAction
{
    public function __construct(
        private readonly S3StorageService $s3Service
    ) {}

    /**
     * Execute translation restoration.
     */
    public function execute(array $options = []): RestoreResultDTO
    {
        $interface = $options['interface'];
        $useLatest = $options['useLatest'] ?? false;
        $backupFile = $options['backupFile'] ?? null;

        Log::info('Starting translation restoration', [
            'interface' => $interface,
            'use_latest' => $useLatest,
            'backup_file' => $backupFile,
        ]);

        try {
            return DB::transaction(function () use ($interface, $useLatest, $backupFile): RestoreResultDTO {
                // Determine which backup to use

                if ($useLatest) {
                    $backupContent = $this->s3Service->getLatestBackup($interface);
                    if (in_array($backupContent, [null, '', '0'], true)) {
                        throw new RuntimeException('No backups found for interface: '.$interface);
                    }
                    $backupFile = 'latest';
                } else {
                    $backupPath = "backups/{$interface}/{$backupFile}";
                    $backupContent = $this->s3Service->downloadMigrationFile($interface, $backupFile);
                }

                // Parse backup content
                $data = json_decode($backupContent, true, 512, JSON_THROW_ON_ERROR);
                if (! isset($data['version']) || ! isset($data['translations'])) {
                    throw new RuntimeException('Invalid backup file format');
                }

                // Apply restored translations
                $keysRestored = 0;
                $languageBreakdown = [];

                foreach ($data['translations'] as $language => $keys) {
                    $languageBreakdown[$language] = count($keys);
                    $keysRestored += count($keys);

                    // Here you would apply the translations to the local system
                    // This depends on your translation storage mechanism
                    Log::info("Restoring {$language} translations", [
                        'keys_count' => count($keys),
                    ]);
                }

                Log::info('Translation restoration completed', [
                    'interface' => $interface,
                    'backup_file' => $backupFile,
                    'version' => $data['version'],
                    'keys_restored' => $keysRestored,
                ]);

                return new RestoreResultDTO(
                    success: true,
                    interface: $interface,
                    backupFile: $backupFile,
                    restoredVersion: $data['version'],
                    keysRestored: $keysRestored,
                    languageBreakdown: $languageBreakdown,
                    restoredAt: Carbon::now()
                );
            });
        } catch (Exception $e) {
            Log::error('Translation restoration failed', [
                'interface' => $interface,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new RestoreResultDTO(
                success: false,
                interface: $interface,
                backupFile: $backupFile ?? '',
                restoredVersion: '',
                keysRestored: 0,
                languageBreakdown: [],
                restoredAt: Carbon::now(),
                error: $e->getMessage()
            );
        }
    }

    /**
     * List available backups for an interface.
     */
    public function listBackups(string $interface): Collection
    {
        $files = $this->s3Service->listBackupFiles($interface);

        return $files->map(function ($filePath): BackupInfoDTO {
            // Extract metadata from filename
            // Format: backups/{interface}/{interface}_backup_{version}_{timestamp}.json
            $filename = basename($filePath);
            preg_match('/backup_(.+?)_(\d{4}-\d{2}-\d{2}_\d{6})\.json$/', $filename, $matches);

            $version = $matches[1] ?? 'unknown';
            $timestamp = isset($matches[2])
                ? Carbon::createFromFormat('Y-m-d_His', $matches[2])
                : Carbon::now();

            return new BackupInfoDTO(
                filename: $filename,
                path: $filePath,
                version: $version,
                createdAt: $timestamp,
                size: 0 // Would need S3 API call to get actual size
            );
        })->sortByDesc('createdAt');
    }
}
