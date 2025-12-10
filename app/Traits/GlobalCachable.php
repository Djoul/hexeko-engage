<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\User;
use DB;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Log;

trait GlobalCachable
{
    use Cachable;

    /**
     * Retrieve a model by ID from cache (or DB if not cached).
     *
     * @param  string  $id  The ID of the model to find
     * @param  array<int, string>  $relations  The relations to load with the model
     * @param  bool  $pipeFiltered  Whether to apply pipe filtering to the query
     * @param  bool  $related  Whether to apply the related scope if it exists
     * @return static|null The found model or null if not found
     */
    public static function findCached(string $id, array $relations = [], bool $pipeFiltered = false, bool $related = false): ?static
    {
        $idSuffix = (Auth::check() && Auth::id()) ? '_'.Auth::id() : '';
        $cacheKey = cache_key(
            class_basename(static::class),
            $id.$idSuffix,
            $relations === [] ? null : md5((string) json_encode($relations))
        );

        $cacheTag = method_exists(static::make(), 'getCacheTag')
            ? static::make()->getCacheTag($id)
            : cache_tag(class_basename(static::class), $id);

        return Cache::tags($cacheTag)
            ->rememberForever($cacheKey, function () use ($id, $relations, $pipeFiltered, $related): static|null {
                /** @var Builder<static> $query */
                $query = static::with($relations);
                if (
                    $pipeFiltered &&
                    // @phpstan-ignore-next-line
                    (method_exists(static::class, 'scopePipeFiltered') || method_exists(static::class, 'pipeFiltered'))
                ) {
                    // @phpstan-ignore-next-line
                    $query = $query->pipeFiltered();
                }

                // Apply related scope if requested and method exists
                if ($related && method_exists(static::class, 'scopeRelated')) {
                    // @phpstan-ignore-next-line
                    $query = $query->related();
                }

                /** @var static|null $result */
                $result = $query->find($id);

                return $result;
            });
    }

    /**
     * Refresh the cache for all models.
     *
     * @param  bool  $withResponse  Whether to return the refreshed models
     * @return Collection<int, static>|null
     */
    public static function refreshAllCache(bool $withResponse = false): ?Collection
    {
        // Clear all possible cache key variations for 'all' collections
        $baseClass = class_basename(static::class);

        // Use SCAN instead of KEYS for Redis Cluster compatibility
        try {
            $cache = Cache::getStore();

            if (method_exists($cache, 'connection')) {
                // For Redis cache store
                $redis = $cache->connection();

                // Patterns to match - looking for anything with the model name
                $patterns = [
                    '*'.$baseClass.'*',               // Match any key with the model name
                    '*'.strtolower($baseClass).'*',   // Lowercase version
                    '*entity_'.$baseClass.'*',        // With entity prefix (Redis cluster)
                ];

                $allKeys = [];

                // Use SCAN instead of KEYS for Redis Cluster compatibility
                foreach ($patterns as $pattern) {
                    $cursor = 0;
                    do {
                        // SCAN is Redis Cluster compatible
                        [$cursor, $keys] = $redis->scan($cursor, ['match' => $pattern, 'count' => 100]);

                        if ($keys) {
                            $allKeys = array_merge($allKeys, $keys);
                        }
                    } while ($cursor != 0);
                }

                // Also scan with common prefixes
                $prefixPatterns = [
                    'upengage_cache_*'.$baseClass.'*',
                    'upengage_database_*'.$baseClass.'*',
                ];

                foreach ($prefixPatterns as $pattern) {
                    $cursor = 0;
                    do {
                        [$cursor, $keys] = $redis->scan($cursor, ['match' => $pattern, 'count' => 100]);

                        if ($keys) {
                            $allKeys = array_merge($allKeys, $keys);
                        }
                    } while ($cursor != 0);
                }

                // Remove duplicates
                $allKeys = array_unique($allKeys);

                if ($allKeys !== []) {
                    // Delete all found keys directly
                    // Use pipeline for better performance
                    $pipe = $redis->pipeline();
                    foreach ($allKeys as $key) {
                        $pipe->del($key);
                    }
                    $pipe->execute();
                }

                // Also try to clear using Laravel's Cache facade with common patterns
                // This helps clear keys that might be managed differently by Laravel
                $userIds = DB::table('users')->pluck('id')->toArray();
                foreach ($userIds as $userId) {
                    // Clear cache for each user
                    $userIdString = is_scalar($userId) ? (string) $userId : '';
                    $userCacheKey = cache_key($baseClass, 'all_'.$userIdString);
                    Cache::forget($userCacheKey);

                    // Try with various suffixes that might exist
                    $keysToTry = [
                        cache_key($baseClass, 'all_'.$userIdString),
                        cache_key($baseClass, $userIdString),
                        cache_key(strtolower($baseClass), 'all_'.$userIdString),
                    ];

                    foreach ($keysToTry as $key) {
                        Cache::forget($key);
                    }
                }

                // Clear generic keys without user ID
                Cache::forget(cache_key($baseClass, 'all'));
                Cache::forget(cache_key($baseClass, ''));
                Cache::forget(cache_key(strtolower($baseClass), 'all'));

            } else {
                // Fallback for non-Redis cache stores
                // Clear basic 'all' cache key
                $cacheKey = cache_key($baseClass, 'all');
                Cache::forget($cacheKey);

                // Clear user-specific cache keys if authenticated
                if (Auth::check() && Auth::id()) {
                    $userCacheKey = cache_key($baseClass, 'all_'.Auth::id());
                    Cache::forget($userCacheKey);
                }
            }
        } catch (Exception $e) {
            Log::warning('Cache pattern clear failed, falling back to basic clear', [
                'class' => $baseClass,
                'error' => $e->getMessage(),
            ]);

            // Fallback: clear at least the current user's cache
            $cacheKey = cache_key($baseClass, 'all');
            Cache::forget($cacheKey);
        }

        // Try to flush cache tags (may fail with Redis Cluster)
        try {
            $cacheTag = cache_tag($baseClass, 'all');
            Cache::tags([$cacheTag])->flush();
        } catch (Exception $e) {
            // Silently handle Redis cluster errors
            Log::debug('Cache tag flush failed (expected with Redis cluster)', [
                'class' => $baseClass,
                'error' => $e->getMessage(),
            ]);
        }

        if ($withResponse) {
            return static::allCached();
        }

        return null;
    }

    /**
     * Retrieve all models from cache (or DB if not cached).
     *
     * @param  array<int, string>  $relations  The relations to load with the models
     * @param  bool  $pipeFiltered  Whether to apply the pipe filter to the query
     * @param  bool  $related  Whether to apply the related scope if it exists
     * @return Collection<int, static>
     */
    public static function allCached(array $relations = [], bool $pipeFiltered = false, bool $related = false): Collection
    {

        $idSuffix = (Auth::check() && Auth::id()) ? '_'.Auth::id() : '';

        /* Cache key structure rules:
         1. Base components:
            - Model class name (identifies model type)
            - 'all' string (indicates full collection)
            - User ID suffix if authenticated (per-user cache isolation)

         2. Request-specific components:
            - Relations array hash (when relations specified)
            - Query string hash (when pipe filtered)

         3. Cache key generation:
            - MD5 hash used to:
              a) Ensure valid cache key string
              b) Handle arbitrary length inputs
              c) Maintain consistent key length

         4. Cache differentiation strategy:
            - Without relations: Query string used as differentiator
            - With relations: Combined hash of relations + query string
            This ensures unique caches per:
            - Individual user sessions
            - Specific relation combinations
            - Distinct filter parameters */

        // Safely encode request query
        $queryData = request()->query();
        $encodedQuery = json_encode($queryData);
        if ($encodedQuery === false) {
            $encodedQuery = '[]';
        }

        // Safely encode relations
        $encodedRelations = json_encode($relations);
        if ($encodedRelations === false) {
            $encodedRelations = '[]';
        }

        // Determine the cache key suffix based on whether relations are provided
        $keySuffix = null;
        if ($relations !== []) {
            // With relations - combine query string (if pipe filtered) and relations
            $prefix = $pipeFiltered ? $encodedQuery.'_' : '';
            $keySuffix = md5($prefix.$encodedRelations);
        }

        // Add user financers to cache key when related filtering is enabled
        $relatedSuffix = '';
        if ($related && Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            // Use query to avoid loading unnecessary relations
            $financerIds = $user->financers()->pluck('financers.id')->sort()->values()->toArray();
            $encodedFinancerIds = json_encode($financerIds);
            if ($encodedFinancerIds === false) {
                $encodedFinancerIds = '[]';
            }
            $relatedSuffix = '_financers_'.md5($encodedFinancerIds);
        }

        $cacheKey = cache_key(
            class_basename(static::class),
            'all'.$idSuffix,
            $keySuffix.$related.$relatedSuffix
        );

        $cacheTag = method_exists(static::make(), 'getCacheTag')
            ? static::make()->getCacheTag()
            : cache_tag(class_basename(static::class), 'all');

        return Cache::tags($cacheTag)->rememberForever(
            $cacheKey,
            /**
             * @return \Illuminate\Database\Eloquent\Collection
             */
            function () use ($relations, $pipeFiltered, $related): Collection {
                /** @var Builder<static> $query */
                $query = static::with($relations);
                if (
                    $pipeFiltered &&
                    // @phpstan-ignore-next-line
                    (method_exists(static::class, 'scopePipeFiltered') || method_exists(static::class, 'pipeFiltered'))
                ) {
                    // @phpstan-ignore-next-line
                    $query = $query->pipeFiltered();
                }

                // Apply related scope if requested and method exists
                if ($related && (method_exists(static::class, 'scopeRelated') || method_exists(static::class, 'related'))) {
                    // @phpstan-ignore-next-line
                    $query = $query->related();
                }

                /** @var Collection<int, static> $result */
                $result = $query->get();

                return $result;
            }
        );
    }
}
