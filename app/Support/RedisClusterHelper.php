<?php

namespace App\Support;

use DateInterval;
use DateTimeInterface;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use RedisException;

class RedisClusterHelper
{
    /**
     * Generate a cluster-safe cache key using Redis hash tags.
     * Hash tags ensure related keys are stored on the same slot in Redis Cluster.
     */
    public static function key(string $key, ?string $tag = null): string
    {
        $appName = Config::get('app.name', 'laravel');
        $hashTag = $tag ?? $appName;

        // Use Redis hash tags {} to ensure keys are on the same slot
        $hashTagString = is_string($hashTag) ? $hashTag : (is_scalar($hashTag) ? (string) $hashTag : 'default');

        return '{'.$hashTagString."}:$key";
    }

    /**
     * Generate cluster-safe tags for cache operations.
     * All tags will include the app name as a hash tag to ensure they're on the same slot.
     *
     * @param  array<int, string>  $tags
     * @return array<int, string>
     */
    public static function tags(array $tags): array
    {
        $appName = Config::get('app.name', 'laravel');

        return array_map(function (string $tag) use ($appName): string {
            // If the tag already has hash tags, don't modify it
            if (str_contains($tag, '{') && str_contains($tag, '}')) {
                return $tag;
            }

            // Add hash tags to ensure all tags are on the same slot
            $appNameString = is_string($appName) ? $appName : (is_scalar($appName) ? (string) $appName : 'default');

            return '{'.$appNameString."}:tag:$tag";
        }, $tags);
    }

    /**
     * Get a cache instance with cluster-safe tags.
     *
     * @param  array<int, string>  $tags
     * @return Repository|\Illuminate\Contracts\Cache\Repository
     */
    public static function cache(array $tags = [])
    {
        if ($tags === []) {
            return Cache::store();
        }

        return Cache::tags(self::tags($tags));
    }

    /**
     * Flush cache tags in a cluster-safe way.
     *
     * @param  array<int, string>  $tags
     */
    public static function flush(array $tags): bool
    {
        try {
            // Use the cache method which already handles cluster-safe tags
            $cacheInstance = self::cache($tags);
            if (method_exists($cacheInstance, 'flush')) {
                return $cacheInstance->flush();
            }

            return Cache::flush();
        } catch (RedisException $e) {
            // If CROSSSLOT error occurs, return true as cache will be invalidated anyway
            if (str_contains($e->getMessage(), 'CROSSSLOT')) {
                return true;
            }
            throw $e;
        }
    }

    /**
     * Remember a value in cache with cluster-safe keys and tags.
     *
     * @param  array<int, string>  $tags
     */
    public static function remember(string $key, DateTimeInterface|DateInterval|int|null $ttl, callable $callback, array $tags = [], ?string $hashTag = null): mixed
    {
        $cacheKey = self::key($key, $hashTag);

        if ($tags === []) {
            return Cache::remember($cacheKey, $ttl, function () use ($callback) {
                return $callback();
            });
        }

        return self::cache($tags)->remember($cacheKey, $ttl, function () use ($callback) {
            return $callback();
        });
    }

    /**
     * Remember a value forever in cache with cluster-safe keys and tags.
     *
     * @param  array<int, string>  $tags
     */
    public static function rememberForever(string $key, callable $callback, array $tags = [], ?string $hashTag = null): mixed
    {
        $cacheKey = self::key($key, $hashTag);

        if ($tags === []) {
            return Cache::rememberForever($cacheKey, function () use ($callback) {
                return $callback();
            });
        }

        return self::cache($tags)->rememberForever($cacheKey, function () use ($callback) {
            return $callback();
        });
    }

    /**
     * Put a value in cache with cluster-safe keys and tags.
     *
     * @param  array<int, string>  $tags
     */
    public static function put(string $key, mixed $value, DateTimeInterface|DateInterval|int|null $ttl = null, array $tags = [], ?string $hashTag = null): bool
    {
        $cacheKey = self::key($key, $hashTag);

        if ($tags === []) {
            return Cache::put($cacheKey, $value, $ttl);
        }

        return self::cache($tags)->put($cacheKey, $value, $ttl);
    }

    /**
     * Get a value from cache with cluster-safe keys and tags.
     *
     * @param  array<int, string>  $tags
     */
    public static function get(string $key, mixed $default = null, array $tags = [], ?string $hashTag = null): mixed
    {
        $cacheKey = self::key($key, $hashTag);

        if ($tags === []) {
            return Cache::get($cacheKey, $default);
        }

        return self::cache($tags)->get($cacheKey, $default);
    }

    /**
     * Forget a value from cache with cluster-safe keys and tags.
     *
     * @param  array<int, string>  $tags
     */
    public static function forget(string $key, array $tags = [], ?string $hashTag = null): bool
    {
        $cacheKey = self::key($key, $hashTag);

        if ($tags === []) {
            return Cache::forget($cacheKey);
        }

        return self::cache($tags)->forget($cacheKey);
    }
}
