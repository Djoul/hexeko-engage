<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\TranslationMigrations\S3StorageService;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TranslationManifestService
{
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private readonly S3StorageService $s3Service
    ) {}

    /**
     * Get manifest for a specific interface.
     */
    public function getManifest(string $interface): ?array
    {
        $cacheKey = "translation_manifest_{$interface}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($interface) {
            try {
                $content = $this->s3Service->getManifest($interface);

                if ($content === null) {
                    return null;
                }

                $manifest = json_decode($content, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error('Failed to parse manifest JSON', [
                        'interface' => $interface,
                        'error' => json_last_error_msg(),
                    ]);

                    return null;
                }

                return $manifest;
            } catch (Exception $e) {
                Log::error('Failed to fetch manifest', [
                    'interface' => $interface,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Update manifest for a specific interface.
     */
    public function updateManifest(string $interface, array $data): bool
    {
        try {
            // Add metadata if not present
            if (! isset($data['updated_at'])) {
                $data['updated_at'] = now()->toIso8601String();
            }

            if (! isset($data['created_at']) && in_array($this->getManifest($interface), [null, []], true)) {
                $data['created_at'] = now()->toIso8601String();
            }

            $json = json_encode($data, JSON_PRETTY_PRINT);

            $result = $this->s3Service->updateManifest($interface, $json);

            if ($result) {
                // Clear cache after successful update
                Cache::forget("translation_manifest_{$interface}");
            }

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to update manifest', [
                'interface' => $interface,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Validate if a file is approved in the manifest.
     */
    public function validateAgainstManifest(string $interface, string $filename): bool
    {
        $manifest = $this->getManifest($interface);

        // If no manifest exists, allow all files (backward compatibility)
        if ($manifest === null) {
            return true;
        }

        // Check if file is in the approved list
        $approvedFiles = collect($manifest['files'] ?? [])
            ->pluck('filename')
            ->toArray();

        return in_array($filename, $approvedFiles, true);
    }
}
