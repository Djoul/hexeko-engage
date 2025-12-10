<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

class ContentAvailabilityService
{
    private const WELLWO_ENDPOINTS = [
        'recordedClassesGetDisciplines',
        'recordedClassesGetVideoList',
        'recordedProgramsGetPrograms',
        'recordedProgramsGetVideoList',
    ];

    private const VIDEO_ENDPOINTS = [
        'recordedClassesGetVideoList',
        'recordedProgramsGetVideoList',
    ];

    private const DEFAULT_LANGUAGE = 'en';

    private const CACHE_TTL = 300; // 5 minutes

    public function isValidLanguage(string $language): bool
    {
        return in_array(strtolower($language), Config::get('services.wellwo.supported_languages', []), true);
    }

    public function getSupportedLanguages(): array
    {
        return Config::get('services.wellwo.supported_languages', []);
    }

    public function extractContentIds(Collection $collection, ?string $key = null): array
    {
        // Handle empty collections
        if ($collection->isEmpty()) {
            return [];
        }

        // If a specific key is provided, extract from that key
        if ($key !== null && $collection->has($key)) {
            $items = $collection->get($key);
            if (is_array($items)) {
                return collect($items)
                    ->pluck('id')
                    ->filter()
                    ->values()
                    ->toArray();
            }
        }

        // For flat collections, extract IDs directly
        return $collection
            ->pluck('id')
            ->filter()
            ->values()
            ->toArray();
    }

    public function getWellWoEndpoints(): array
    {
        return self::WELLWO_ENDPOINTS;
    }

    public function getCacheKey(string $language): string
    {
        return sprintf('wellwo:availability:%s', strtolower($language));
    }

    public function getCacheTtl(): int
    {
        return self::CACHE_TTL;
    }

    public function normalizeLanguageCode(string $language): string
    {
        return strtolower($language);
    }

    public function getDefaultLanguage(): string
    {
        return self::DEFAULT_LANGUAGE;
    }

    public function isVideoEndpoint(string $endpoint): bool
    {
        return in_array($endpoint, self::VIDEO_ENDPOINTS, true);
    }
}
