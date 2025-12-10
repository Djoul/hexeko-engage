<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class CognitoThrottleMiddleware
{
    private const THROTTLE_LIMITS = [
        'sms' => 10,
        'email' => 5,
    ];

    private const THROTTLE_TTL_SECONDS = 60;

    public function handle(Request $request, Closure $next, string $type): mixed
    {
        // Validate throttle type
        if (! array_key_exists($type, self::THROTTLE_LIMITS)) {
            return response('Invalid throttle type', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Extract identifier from request
        $identifier = $this->extractIdentifier($request);

        // If no identifier found, skip throttling and let controller handle validation
        if ($identifier === null) {
            return $next($request);
        }

        // Normalize and hash identifier for RGPD compliance
        $hash = $this->hashIdentifier($identifier);

        // Build cache key with type-specific bucket
        $cacheKey = "cognito:throttle:{$type}:{$hash}";

        // Get current count
        $cachedCount = Cache::get($cacheKey, 0);
        $currentCount = is_int($cachedCount) ? $cachedCount : 0;

        // Check if limit exceeded
        $limit = self::THROTTLE_LIMITS[$type];

        if ($currentCount >= $limit) {
            $cachedTtl = Cache::get($cacheKey.':ttl', self::THROTTLE_TTL_SECONDS);
            $ttl = is_int($cachedTtl) ? $cachedTtl : self::THROTTLE_TTL_SECONDS;
            $retryAfter = max(1, $ttl);

            return response('Too many requests. Please try again later.', Response::HTTP_TOO_MANY_REQUESTS)
                ->header('Retry-After', (string) $retryAfter);
        }

        // Increment counter
        if ($currentCount === 0) {
            // First request - set counter and TTL
            Cache::put($cacheKey, 1, self::THROTTLE_TTL_SECONDS);
            Cache::put($cacheKey.':ttl', self::THROTTLE_TTL_SECONDS, self::THROTTLE_TTL_SECONDS);
        } else {
            // Increment counter (keep existing TTL)
            Cache::increment($cacheKey);
        }

        return $next($request);
    }

    private function extractIdentifier(Request $request): ?string
    {
        // Try email first
        $email = $request->input('email');
        if (is_string($email) && $email !== '') {
            return $email;
        }

        // Try phone_number
        $phoneNumber = $request->input('phone_number');
        if (is_string($phoneNumber) && $phoneNumber !== '') {
            return $phoneNumber;
        }

        return null;
    }

    private function hashIdentifier(string $identifier): string
    {
        $normalized = $this->normalizeIdentifier($identifier);

        return hash('sha256', $normalized);
    }

    private function normalizeIdentifier(string $identifier): string
    {
        return strtolower(trim($identifier));
    }
}
