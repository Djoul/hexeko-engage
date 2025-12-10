<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Storage;

use App\Integrations\Wellbeing\WellWo\DTOs\ContentAvailabilityDTO;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;

class ContentAvailabilityStorage
{
    private string $disk;

    public function __construct(?string $disk = null)
    {
        if ($disk === null) {
            $this->disk = app()->environment(['local', 'testing'])
                ? 's3-local'
                : 's3';
        } else {
            $this->disk = $disk;
        }
    }

    public function getDisk(): string
    {
        return $this->disk;
    }

    public function saveAvailability(string $language, ContentAvailabilityDTO $dto): bool
    {
        $path = $this->getPath($language);
        $tempPath = $path.'.tmp';

        try {
            Log::debug('S3 upload attempt', [
                'disk' => $this->disk,
                'path' => $path,
                'language' => $language,
                'content_size' => strlen($dto->toJson()),
            ]);

            // Ensure UTF-8 encoding
            $content = mb_convert_encoding($dto->toJson(), 'UTF-8', 'UTF-8');

            // Write to temporary file first (atomic update)
            $result = Storage::disk($this->disk)->put(
                $tempPath,
                $content,
                ['Content-Type' => 'application/json; charset=utf-8']
            );

            if ($result === false) {
                Log::error('S3 upload failed - put() returned false', [
                    'disk' => $this->disk,
                    'path' => $tempPath,
                ]);
                throw new RuntimeException("S3 upload failed for {$tempPath}");
            }

            // Move temp file to final location (atomic)
            Storage::disk($this->disk)->move($tempPath, $path);

            Log::info('S3 upload successful', [
                'disk' => $this->disk,
                'path' => $path,
                'language' => $language,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('S3 upload exception', [
                'message' => $e->getMessage(),
                'disk' => $this->disk,
                'path' => $path,
                'language' => $language,
                'trace' => $e->getTraceAsString(),
            ]);

            // Clean up temp file if it exists
            if (Storage::disk($this->disk)->exists($tempPath)) {
                Storage::disk($this->disk)->delete($tempPath);
            }

            throw new RuntimeException(
                "Failed to upload to S3: {$e->getMessage()} [Disk: {$this->disk}, Path: {$path}]",
                0,
                $e
            );
        }
    }

    public function loadAvailability(string $language): ?ContentAvailabilityDTO
    {
        $path = $this->getPath($language);
        if (! Storage::disk($this->disk)->exists($path)) {
            return null;
        }

        try {
            $content = Storage::disk($this->disk)->get($path);
            if ($content === null) {
                throw new RuntimeException("Could not read availability file: {$path}");
            }

            return ContentAvailabilityDTO::fromJson($content);

        } catch (JsonException $e) {
            Log::error('Failed to parse availability JSON', [
                'language' => $language,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Failed to load availability data', [
                'language' => $language,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function deleteAvailability(string $language): bool
    {
        $path = $this->getPath($language);

        return Storage::disk($this->disk)->delete($path);
    }

    public function listAvailableLanguages(): array
    {
        $basePath = 'wellwo/availability/';
        $directories = Storage::disk($this->disk)->directories($basePath);

        $languages = [];
        foreach ($directories as $dir) {
            // Extract language code from path
            $language = basename($dir);

            // Check if content.json exists for this language
            if (Storage::disk($this->disk)->exists("{$dir}/content.json")) {
                $languages[] = $language;
            }
        }

        return $languages;
    }

    private function getPath(string $language): string
    {
        return "wellwo/availability/{$language}/content.json";
    }
}
