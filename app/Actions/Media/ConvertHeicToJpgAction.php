<?php

declare(strict_types=1);

namespace App\Actions\Media;

use Exception;
use Maestroerror\HeicToJpg;
use RuntimeException;

/**
 * Action to convert HEIC images to JPG format
 *
 * This action handles the conversion of HEIC (High Efficiency Image Container)
 * images to JPG format using the php-heic-to-jpg library.
 */
class ConvertHeicToJpgAction
{
    /**
     * Execute the conversion from HEIC to JPG
     *
     * @param  string  $base64HeicData  Base64 encoded HEIC image with data URI prefix
     * @return string Base64 encoded JPG image with data URI prefix
     *
     * @throws RuntimeException If conversion fails
     */
    public function execute(string $base64HeicData): string
    {
        try {
            // Extract the actual base64 data (remove data:image/heic;base64, prefix if present)
            $base64Data = $this->extractBase64Data($base64HeicData);

            // Decode base64 to binary
            $heicBinary = base64_decode($base64Data, true);
            if ($heicBinary === false) {
                throw new RuntimeException('Failed to decode base64 HEIC data');
            }

            // Create temporary file for HEIC
            $tempHeicPath = $this->createTempFile($heicBinary, 'heic');

            try {
                // Convert HEIC to JPG using php-heic-to-jpg
                $heicToJpg = new HeicToJpg;
                $jpgBinary = $heicToJpg->convert($tempHeicPath)->get();

                if (empty($jpgBinary)) {
                    throw new RuntimeException('HEIC to JPG conversion failed - empty output');
                }

                // Encode back to base64 with data URI prefix
                $base64Jpg = base64_encode($jpgBinary);

                return 'data:image/jpeg;base64,'.$base64Jpg;
            } finally {
                // Always clean up temp HEIC file
                if (file_exists($tempHeicPath)) {
                    unlink($tempHeicPath);
                }
            }
        } catch (Exception $e) {
            throw new RuntimeException(
                'HEIC to JPG conversion failed: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Extract pure base64 data from data URI
     */
    protected function extractBase64Data(string $dataUri): string
    {
        // If it starts with data:, extract the base64 part
        if (str_starts_with($dataUri, 'data:')) {
            $parts = explode(',', $dataUri, 2);

            return $parts[1] ?? $dataUri;
        }

        return $dataUri;
    }

    /**
     * Create a temporary file with the given binary data
     *
     * @throws RuntimeException If file creation fails
     */
    protected function createTempFile(string $binaryData, string $extension): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'heic_').'.'.$extension;

        if (file_put_contents($tempPath, $binaryData) === false) {
            throw new RuntimeException('Failed to create temporary file');
        }

        return $tempPath;
    }
}
