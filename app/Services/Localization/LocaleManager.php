<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Enums\Languages;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * LocaleManager V3 - Cognito-aware locale determination with RGPD-compliant caching
 *
 * Fallback priority:
 * 1. Cognito custom:reg_language attribute
 * 2. User.locale accessor (financer_user.language → users.locale)
 * 3. Languages::FRENCH (default for Cognito notifications)
 *
 * Features:
 * - Redis caching with SHA256 hashed identifiers (RGPD compliant)
 * - Scoped locale execution pattern
 * - Cache invalidation on user updates
 */
class LocaleManager
{
    private const CACHE_TTL = 3600; // 1 hour

    private const CACHE_PREFIX = 'locale:cognito:';

    /**
     * Determine locale from Cognito data with fallback chain.
     *
     * @param  array<string, mixed>  $cognitoData  Cognito user attributes
     * @param  string  $userId  User UUID
     * @return string Language code (e.g., 'fr-FR')
     */
    public function determineFromCognito(array $cognitoData, string $userId): string
    {
        // Extract identifier for caching (email preferred)
        $identifier = $cognitoData['email'] ?? $cognitoData['phone_number'] ?? $userId;
        $cacheKey = $this->getCacheKey($identifier);

        // Check cache first
        /** @var string|null $cached */
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Priority 1: Cognito custom:reg_language attribute
        if (isset($cognitoData['custom:reg_language']) && $cognitoData['custom:reg_language'] !== '') {
            $locale = $this->normalizeLocale($cognitoData['custom:reg_language']);
            $this->cacheLocale($cacheKey, $locale);

            return $locale;
        }

        // Priority 2: User.locale accessor (includes financer pivot fallback)
        // Load with financers to allow accessor to check financer_user.language
        $user = User::with('financers')->find($userId);
        if ($user !== null && $user->locale !== null && $user->locale !== '') {
            $locale = $user->locale;
            $this->cacheLocale($cacheKey, $locale);

            return $locale;
        }

        // Priority 3: Default to French for Cognito notifications
        $locale = Languages::FRENCH;
        $this->cacheLocale($cacheKey, $locale);

        Log::info('LocaleManager defaulted to French', [
            'user_id' => $userId,
            'identifier_hash' => $this->hashIdentifier($identifier),
        ]);

        return $locale;
    }

    /**
     * Execute callback with scoped locale, then restore original.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withLocale(string $locale, callable $callback): mixed
    {
        $original = app()->getLocale();

        try {
            app()->setLocale($locale);

            return $callback();
        } finally {
            app()->setLocale($original);
        }
    }

    /**
     * Set application locale (non-scoped).
     */
    public function setScoped(string $locale): void
    {
        app()->setLocale($locale);
    }

    /**
     * Restore application locale to original.
     */
    public function restore(): void
    {
        app()->setLocale(config('app.locale'));
    }

    /**
     * Invalidate cached locale for a given identifier.
     */
    public function invalidateCache(string $identifier): void
    {
        $cacheKey = $this->getCacheKey($identifier);
        Cache::forget($cacheKey);

        Log::debug('LocaleManager cache invalidated', [
            'identifier_hash' => $this->hashIdentifier($identifier),
        ]);
    }

    /**
     * Hash identifier using SHA256 (RGPD-compliant PII protection).
     *
     * Normalizes before hashing to ensure consistent results:
     * - Trim whitespace
     * - Lowercase
     */
    public function hashIdentifier(string $identifier): string
    {
        $normalized = $this->normalizeIdentifier($identifier);

        return hash('sha256', $normalized);
    }

    /**
     * Normalize identifier for consistent hashing.
     */
    private function normalizeIdentifier(string $identifier): string
    {
        return strtolower(trim($identifier));
    }

    /**
     * Get cache key for identifier (hashed).
     */
    private function getCacheKey(string $identifier): string
    {
        $hash = $this->hashIdentifier($identifier);

        return self::CACHE_PREFIX.$hash;
    }

    /**
     * Cache locale for identifier.
     */
    private function cacheLocale(string $cacheKey, string $locale): void
    {
        Cache::put($cacheKey, $locale, self::CACHE_TTL);
    }

    /**
     * Normalize locale string (trim, validate).
     */
    private function normalizeLocale(string $locale): string
    {
        return trim($locale);
    }
}
