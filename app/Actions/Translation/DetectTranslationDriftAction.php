<?php

declare(strict_types=1);

namespace App\Actions\Translation;

use App\DTOs\Translation\TranslationDriftDTO;
use App\Services\TranslationMigrations\S3StorageService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class DetectTranslationDriftAction
{
    public function __construct(
        private readonly S3StorageService $s3Service
    ) {}

    /**
     * Execute drift detection for translation files.
     */
    public function execute(array $options = []): TranslationDriftDTO
    {
        $interfaceFilter = $options['interface'] ?? null;
        $interfaces = $interfaceFilter
            ? [$interfaceFilter]
            : ['mobile', 'web_financer', 'web_beneficiary'];

        $interfaceData = [];
        $hasDrift = false;

        foreach ($interfaces as $interface) {
            $driftData = $this->checkInterfaceDrift($interface);
            $interfaceData[$interface] = $driftData;

            if ($driftData['has_drift']) {
                $hasDrift = true;
            }
        }

        Log::info('Translation drift detection completed', [
            'has_drift' => $hasDrift,
            'interfaces_checked' => count($interfaces),
            'interfaces_with_drift' => count(array_filter($interfaceData, fn (array $d) => $d['has_drift'])),
        ]);

        return new TranslationDriftDTO(
            hasDrift: $hasDrift,
            checkedAt: Carbon::now(),
            interfaces: $interfaceData
        );
    }

    /**
     * Check drift for a specific interface.
     */
    private function checkInterfaceDrift(string $interface): array
    {
        try {
            // Get S3 files
            $s3Files = $this->s3Service->listMigrationFiles($interface);
            $s3FileNames = $s3Files->map(fn ($path): string => basename($path))->toArray();

            // Get local files (would need actual implementation)
            $localFiles = $this->getLocalFiles();
            $localFileNames = array_keys($localFiles);

            // Find differences
            $missingInLocal = array_diff($s3FileNames, $localFileNames);
            $missingInS3 = array_diff($localFileNames, $s3FileNames);

            // Check checksums for common files
            $checksumMismatches = [];
            $commonFiles = array_intersect($s3FileNames, $localFileNames);

            foreach ($commonFiles as $filename) {
                $s3Checksum = $this->s3Service->getFileChecksum($interface, $filename);
                $localChecksum = $this->getLocalFileChecksum();

                if ($s3Checksum !== $localChecksum) {
                    $checksumMismatches[] = $filename;
                }
            }

            $hasDrift = $missingInLocal !== [] || $missingInS3 !== [] || $checksumMismatches !== [];

            return [
                'name' => $interface,
                'has_drift' => $hasDrift,
                'missing_in_local' => array_values($missingInLocal),
                'missing_in_s3' => array_values($missingInS3),
                'checksum_mismatches' => $checksumMismatches,
            ];

        } catch (Exception $e) {
            Log::error('Drift detection failed for interface', [
                'interface' => $interface,
                'error' => $e->getMessage(),
            ]);

            return [
                'name' => $interface,
                'has_drift' => false,
                'missing_in_local' => [],
                'missing_in_s3' => [],
                'checksum_mismatches' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get local translation files for an interface.
     */
    private function getLocalFiles(): array
    {
        // This would fetch actual local translation files
        // Implementation depends on your translation storage mechanism
        return [];
    }

    /**
     * Calculate checksum for a local file.
     */
    private function getLocalFileChecksum(): string
    {
        // This would calculate checksum for local file
        // Implementation depends on your translation storage mechanism
        return '';
    }
}
