<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TranslationCacheService
{
    private const CACHE_PREFIX = 'translations:';

    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Clear cache for a specific interface.
     */
    public function clearInterface(string $interface): void
    {
        $tag = self::CACHE_PREFIX.$interface;

        Cache::tags([$tag])->flush();

        Log::info('Translation cache cleared', [
            'interface' => $interface,
            'tag' => $tag,
        ]);
    }

    /**
     * Clear all translation caches.
     */
    public function clearAll(): void
    {
        $interfaces = ['mobile', 'web_financer', 'web_beneficiary'];

        foreach ($interfaces as $interface) {
            $this->clearInterface($interface);
        }

        Log::info('All translation caches cleared');
    }

    /**
     * Get cache key for interface.
     */
    public function getCacheKey(string $interface, string $suffix = ''): string
    {
        return self::CACHE_PREFIX.$interface.($suffix !== '' && $suffix !== '0' ? ':'.$suffix : '');
    }

    /**
     * Get cache TTL.
     */
    public function getCacheTtl(): int
    {
        return self::CACHE_TTL;
    }
}
