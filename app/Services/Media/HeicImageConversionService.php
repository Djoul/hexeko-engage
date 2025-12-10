<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Actions\Media\ConvertHeicToJpgAction;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling HEIC image detection and conversion
 *
 * This service automatically detects HEIC images and converts them to JPG
 * format before processing by Spatie Media Library.
 */
class HeicImageConversionService
{
    public function __construct(
        private readonly ConvertHeicToJpgAction $convertHeicToJpgAction
    ) {}

    /**
     * Process an image: convert to JPG if HEIC, otherwise return as-is
     *
     * @param  string  $base64Image  Base64 encoded image with data URI prefix
     * @return string Processed base64 image (converted to JPG if was HEIC)
     */
    public function processImage(string $base64Image): string
    {
        if ($this->isHeicImage($base64Image)) {
            Log::info('HEIC image detected, converting to JPG');

            try {
                $convertedImage = $this->convertHeicToJpgAction->execute($base64Image);

                Log::info('HEIC image successfully converted to JPG');

                return $convertedImage;
            } catch (Exception $e) {
                Log::error('HEIC to JPG conversion failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Re-throw to let the caller handle the error
                throw $e;
            }
        }

        // Not a HEIC image, return as-is
        return $base64Image;
    }

    /**
     * Check if a base64 image is in HEIC format
     *
     * @param  string  $base64Image  Base64 encoded image with data URI prefix
     */
    public function isHeicImage(string $base64Image): bool
    {
        // Check if the data URI indicates HEIC/HEIF format
        if (preg_match('/^data:image\/(heic|heif)/i', $base64Image)) {
            return true;
        }

        // For images without data URI prefix, check the binary signature
        // HEIC files typically start with 'ftypheic' or 'ftypheix' or 'ftyphevc'
        $base64Data = $this->extractBase64Data($base64Image);
        $binaryData = base64_decode($base64Data, true);

        if ($binaryData === false) {
            return false;
        }

        // Check for HEIC/HEIF file signatures
        // HEIC files have 'ftyp' at offset 4, followed by 'heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'hevm', 'hevs', 'mif1'
        if (strlen($binaryData) >= 12) {
            $signature = substr($binaryData, 4, 8);

            return str_contains($signature, 'heic')
                || str_contains($signature, 'heix')
                || str_contains($signature, 'hevc')
                || str_contains($signature, 'hevx')
                || str_contains($signature, 'heim')
                || str_contains($signature, 'heis')
                || str_contains($signature, 'hevm')
                || str_contains($signature, 'hevs')
                || str_contains($signature, 'mif1');
        }

        return false;
    }

    /**
     * Extract pure base64 data from data URI
     */
    protected function extractBase64Data(string $dataUri): string
    {
        if (str_starts_with($dataUri, 'data:')) {
            $parts = explode(',', $dataUri, 2);

            return $parts[1] ?? $dataUri;
        }

        return $dataUri;
    }
}
