<?php

declare(strict_types=1);

namespace App\Actions\Translation;

use App\DTOs\Translation\TranslationHealthDTO;
use App\Models\TranslationMigration;
use App\Services\TranslationManifestService;
use App\Services\TranslationMigrations\S3StorageService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckTranslationHealthAction
{
    private const CACHE_KEY = 'translation_health_check';

    private const CACHE_TTL = 60; // 1 minute

    public function __construct(
        private readonly S3StorageService $s3Service,
        private readonly TranslationManifestService $manifestService
    ) {}

    /**
     * Execute health check for translation system.
     */
    public function execute(): TranslationHealthDTO
    {
        // Use cache to prevent excessive health checks
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): TranslationHealthDTO {
            return $this->performHealthCheck();
        });
    }

    /**
     * Perform the actual health check.
     */
    private function performHealthCheck(): TranslationHealthDTO
    {
        $interfaces = ['mobile', 'web_financer', 'web_beneficiary'];
        $interfaceData = [];
        $allHealthy = true;

        foreach ($interfaces as $interface) {
            $data = $this->checkInterface($interface);
            $interfaceData[$interface] = $data;

            if ($data['status'] !== 'healthy') {
                $allHealthy = false;
            }
        }

        Log::info('Translation health check completed', [
            'healthy' => $allHealthy,
            'interfaces' => array_map(fn (array $d) => $d['status'], $interfaceData),
        ]);

        return new TranslationHealthDTO(
            healthy: $allHealthy,
            checkedAt: Carbon::now(),
            interfaces: $interfaceData
        );
    }

    /**
     * Check health of a specific interface.
     */
    private function checkInterface(string $interface): array
    {
        $issues = [];
        $status = 'healthy';

        try {
            // Check S3 connectivity
            $s3Files = $this->s3Service->listMigrationFiles($interface);
            $s3FileCount = $s3Files->count();

            // Check pending migrations
            $pendingMigrations = TranslationMigration::pending()
                ->where('interface_origin', $interface)
                ->count();

            if ($pendingMigrations > 10) {
                $issues[] = "High number of pending migrations: {$pendingMigrations}";
                $status = 'warning';
            }

            // Check manifest validity for production
            $manifestValid = true;
            if (app()->environment(['staging', 'production'])) {
                $manifest = $this->manifestService->getManifest($interface);
                if ($manifest === null || $manifest === []) {
                    $issues[] = 'Manifest not found';
                    $manifestValid = false;
                    $status = 'degraded';
                } elseif (isset($manifest['updated_at'])) {
                    $lastUpdate = Carbon::parse($manifest['updated_at']);
                    if ($lastUpdate->diffInDays(now()) > 30) {
                        $issues[] = 'Manifest outdated (>30 days)';
                        $status = 'warning';
                    }
                }
            }

            // Get last sync time
            $lastSync = TranslationMigration::where('interface_origin', $interface)
                ->where('status', 'completed')
                ->orderBy('applied_at', 'desc')
                ->value('applied_at');

            // Check if sync is stale
            if ($lastSync) {
                $hoursSinceSync = Carbon::parse($lastSync)->diffInHours(now());
                if ($hoursSinceSync > 24) {
                    $issues[] = "No sync in {$hoursSinceSync} hours";
                    $status = $status === 'healthy' ? 'warning' : $status;
                }
            }

            // Check for local files (would need actual implementation)
            $localFileCount = $this->countLocalFiles();

            return [
                'name' => $interface,
                'status' => $status,
                'local_files' => $localFileCount,
                's3_files' => $s3FileCount,
                'pending_migrations' => $pendingMigrations,
                'last_sync' => $lastSync ? Carbon::parse($lastSync) : null,
                'manifest_valid' => $manifestValid,
                'issues' => $issues,
            ];

        } catch (Exception $e) {
            Log::error('Health check failed for interface', [
                'interface' => $interface,
                'error' => $e->getMessage(),
            ]);

            return [
                'name' => $interface,
                'status' => 'error',
                'local_files' => 0,
                's3_files' => 0,
                'pending_migrations' => 0,
                'last_sync' => null,
                'manifest_valid' => false,
                'issues' => ['Health check failed: '.$e->getMessage()],
            ];
        }
    }

    /**
     * Count local translation files for an interface.
     */
    private function countLocalFiles(): int
    {
        // This would count actual local translation files
        // Implementation depends on your translation storage mechanism
        return 0;
    }
}
