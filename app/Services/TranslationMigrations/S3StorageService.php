<?php

declare(strict_types=1);

namespace App\Services\TranslationMigrations;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Log;
use RuntimeException;

class S3StorageService
{
    private string $disk;

    /**
     * Get the current disk being used
     */
    public function getDisk(): string
    {
        return $this->disk;
    }

    public function __construct()
    {
        $this->disk = app()->environment(['local', 'testing']) ? 'translations-s3-local' : 'translations-s3';
    }

    public function listMigrationFiles(string $interface): Collection
    {
        $path = "migrations/{$interface}/";

        $files = Storage::disk($this->disk)->files($path);

        return collect($files)->sort()->values();
    }

    public function uploadMigrationFile(string $interface, string $filename, string $content): bool
    {
        $path = "migrations/{$interface}/{$filename}";

        try {
            // Log upload attempt
            Log::debug('S3 upload attempt', [
                'disk' => $this->disk,
                'path' => $path,
                'content_size' => strlen($content),
                'interface' => $interface,
                'filename' => $filename,
            ]);

            // Ensure UTF-8 encoding
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');

            // Attempt to upload with detailed error handling
            $result = Storage::disk($this->disk)->put($path, $content, ['Content-Type' => 'application/json; charset=utf-8']);

            if ($result === false) {
                // Log failure details
                Log::error('S3 upload failed - put() returned false', [
                    'disk' => $this->disk,
                    'path' => $path,
                    'disk_config' => config("filesystems.disks.{$this->disk}"),
                ]);

                // Try to get more error details
                $lastError = error_get_last();
                if ($lastError !== null && $lastError !== []) {
                    Log::error('PHP last error', $lastError);
                }

                throw new RuntimeException("S3 upload failed for {$path} - Storage::put() returned false");
            }

            Log::info('S3 upload successful', [
                'disk' => $this->disk,
                'path' => $path,
                'result' => $result,
            ]);

            return is_bool($result) ? $result : true;

        } catch (Exception $e) {
            // Log complete error with context
            Log::error('S3 upload exception', [
                'message' => $e->getMessage(),
                'disk' => $this->disk,
                'path' => $path,
                'interface' => $interface,
                'filename' => $filename,
                'trace' => $e->getTraceAsString(),
                'disk_config' => config("filesystems.disks.{$this->disk}"),
                'aws_key' => substr(config("filesystems.disks.{$this->disk}.key") ?? '', 0, 4).'****',
                'bucket' => config("filesystems.disks.{$this->disk}.bucket"),
                'region' => config("filesystems.disks.{$this->disk}.region"),
                'endpoint' => config("filesystems.disks.{$this->disk}.endpoint"),
            ]);

            // Re-throw with more context
            throw new RuntimeException(
                "Failed to upload to S3: {$e->getMessage()} [Disk: {$this->disk}, Path: {$path}]",
                0,
                $e
            );
        }
    }

    public function downloadMigrationFile(string $interface, string $filename): string
    {
        $path = "migrations/{$interface}/{$filename}";

        if (! Storage::disk($this->disk)->exists($path)) {
            throw new RuntimeException("Migration file not found: {$path}");
        }

        $content = Storage::disk($this->disk)->get($path);
        if ($content === null) {
            throw new RuntimeException("Could not read migration file: {$path}");
        }

        return $content;
    }

    public function deleteMigrationFile(string $interface, string $filename): bool
    {
        $path = "migrations/{$interface}/{$filename}";

        return Storage::disk($this->disk)->delete($path);
    }

    public function migrationFileExists(string $interface, string $filename): bool
    {
        $path = "migrations/{$interface}/{$filename}";

        return Storage::disk($this->disk)->exists($path);
    }

    /**
     * Create a unified backup with consistent naming across all operations
     *
     * @param  string  $interface  The interface (web, mobile, etc.)
     * @param  string  $operation  Operation type: 'before-import', 'before-migration', 'before-apply', etc.
     * @param  string  $content  The backup content (JSON or CSV)
     * @param  string  $format  File format: 'json' or 'csv'
     */
    public function createUnifiedBackup(string $interface, string $operation, string $content, string $format = 'json'): string
    {
        $timestamp = date('Y-m-d_His');
        $filename = "{$interface}_{$operation}_{$timestamp}.{$format}";
        $path = "backups/{$interface}/{$filename}";

        // Ensure UTF-8 encoding
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');

        // Set appropriate content type
        $contentType = $format === 'csv' ? 'text/csv; charset=utf-8' : 'application/json; charset=utf-8';
        Storage::disk($this->disk)->put($path, $content, ['Content-Type' => $contentType]);

        return $path;
    }

    /**
     * @deprecated Use createUnifiedBackup() instead for consistency
     */
    public function createBackup(string $interface, string $version, string $content): string
    {
        // Keep for backward compatibility but use unified naming
        return $this->createUnifiedBackup($interface, "backup_{$version}", $content, 'json');
    }

    /**
     * @deprecated Use createUnifiedBackup() instead for consistency
     */
    public function createCsvBackup(string $interface, string $content): string
    {
        // Keep for backward compatibility but use unified naming
        return $this->createUnifiedBackup($interface, 'before-import', $content, 'csv');
    }

    public function listBackupFiles(string $interface): Collection
    {
        $path = "backups/{$interface}/";

        $files = Storage::disk($this->disk)->files($path);

        return collect($files);
    }

    public function getPresignedUrl(string $interface, string $filename, int $expirationMinutes = 15): string
    {
        $path = "migrations/{$interface}/{$filename}";

        return Storage::disk($this->disk)->temporaryUrl(
            $path,
            Carbon::now()->addMinutes($expirationMinutes)
        );
    }

    public function getFileChecksum(string $interface, string $filename): string
    {
        $path = "migrations/{$interface}/{$filename}";

        $content = Storage::disk($this->disk)->get($path);
        if ($content === null) {
            throw new RuntimeException("Could not read file for checksum: {$path}");
        }

        return hash('sha256', $content);
    }

    /**
     * Get manifest file for an interface.
     */
    public function getManifest(string $interface): ?string
    {
        $path = "migrations/{$interface}/manifest.json";

        if (! Storage::disk($this->disk)->exists($path)) {
            return null;
        }

        return Storage::disk($this->disk)->get($path);
    }

    /**
     * Update manifest file for an interface.
     */
    public function updateManifest(string $interface, string $content): bool
    {
        $path = "migrations/{$interface}/manifest.json";

        $result = Storage::disk($this->disk)->put($path, $content, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);

        return is_bool($result) ? $result : true;
    }

    /**
     * Get the latest backup file for an interface.
     */
    public function getLatestBackup(string $interface): ?string
    {
        $files = $this->listBackupFiles($interface);

        //        dump($files);
        if ($files->isEmpty()) {
            return null;
        }

        // Sort by filename (which includes timestamp) and get the latest
        $latestFile = $files->sort()->last();
        dump($latestFile);
        $str = Storage::disk($this->disk)->get($latestFile);
        dump($str);

        return $str;
    }
}
