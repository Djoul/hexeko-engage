<?php

declare(strict_types=1);

namespace App\DTOs\Translation;

use App\DTOs\BaseDTO;
use App\Enums\Languages;
use InvalidArgumentException;

class ImportTranslationDTO extends BaseDTO
{
    public string $interfaceOrigin;

    public string $importType; // 'multilingual' or 'single'

    /** @var array<string, mixed> */
    public array $translations;

    public ?string $locale = null;

    public bool $previewOnly = false;

    public bool $updateExistingValues = false;

    /**
     * Parse file content based on import type
     */
    public static function fromFileUpload(
        string $fileContent,
        string $filename,
        string $interfaceOrigin,
        string $importType,
        bool $previewOnly = false,
        bool $updateExistingValues = false
    ): self {
        $data = json_decode($fileContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON file: '.json_last_error_msg());
        }

        $dto = new self;
        $dto->interfaceOrigin = $interfaceOrigin;
        $dto->importType = $importType;
        $dto->previewOnly = $previewOnly;
        $dto->updateExistingValues = $updateExistingValues;

        if ($importType === 'single') {
            // Extract locale from filename (e.g., @en.json -> en -> en-UK)
            $dto->locale = self::extractLocaleFromFilename($filename);
            /** @var array<string, mixed> $data */
            $dto->translations = is_array($data) ? self::convertSingleToMultiFormat($data, $dto->locale) : [];
        } else {
            // Multilingual format - new flat structure
            $dto->translations = is_array($data) && array_key_exists('translations', $data) && is_array($data['translations']) ? $data['translations'] : [];
        }

        return $dto;
    }

    /**
     * Extract locale from filename and convert to full locale
     */
    private static function extractLocaleFromFilename(string $filename): string
    {
        // Remove path if present
        $filename = basename($filename);

        // Extract language code from xx.json or @xx.json pattern
        if (preg_match('/^@?([a-z]{2})\.json$/i', $filename, $matches)) {
            $langCode = strtolower($matches[1]);

            // Map to full locale
            return match ($langCode) {
                'fr' => Languages::FRENCH,
                'en' => Languages::ENGLISH,
                'de' => Languages::GERMAN,
                'nl' => Languages::DUTCH,
                'pt' => Languages::PORTUGUESE,
                'es' => Languages::SPANISH,
                'it' => Languages::ITALIAN,
                default => throw new InvalidArgumentException("Unknown language code: {$langCode}")
            };
        }

        throw new InvalidArgumentException("Invalid filename format. Expected xx.json or @xx.json, got: {$filename}");
    }

    /**
     * Convert single language format to multi format
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function convertSingleToMultiFormat(array $data, string $locale): array
    {
        // Flatten the translations first
        $translations = self::flattenTranslations($data);

        // Convert to multi format with locale
        $multiFormat = [];
        foreach ($translations as $key => $value) {
            $multiFormat[$key] = [$locale => $value];
        }

        return $multiFormat;
    }

    /**
     * Flatten nested translations to dot notation
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function flattenTranslations(
        array $data,
        string $prefix = ''
    ): array {
        $result = [];
        foreach ($data as $key => $value) {
            $fullKey = $prefix !== '' && $prefix !== '0' ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $nested = self::flattenTranslations($value, $fullKey);
                $result = array_merge($result, $nested);
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }

    public function toArray(): array
    {
        return [
            'interface_origin' => $this->interfaceOrigin,
            'import_type' => $this->importType,
            'translations' => $this->translations,
            'locale' => $this->locale,
            'preview_only' => $this->previewOnly,
            'update_existing_values' => $this->updateExistingValues,
        ];
    }
}
