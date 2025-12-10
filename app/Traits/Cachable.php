<?php

declare(strict_types=1);

namespace App\Traits;

use Auth;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Log;

/**
 * Trait allowing automatic caching of Eloquent models.
 *
 * This trait provides methods and events to automatically handle caching and
 * cache invalidation for Eloquent models.
 */
trait Cachable
{
    final public static function getCacheTtl(): int
    {
        // Check if the cacheTtl property is defined in the model
        // @phpstan-ignore-next-line
        return property_exists(static::class, 'cacheTtl') ? static::$cacheTtl : 300;
    }

    /**
     * Boot the cachable trait for a model.
     */
    public static function bootCachable(): void
    {
        static::saved(function (Model $model): void {

            if (method_exists($model, 'handleCacheRefresh')) {
                $model->handleCacheRefresh();
            }
        });

        static::deleted(function (Model $model): void {
            if (method_exists($model, 'clearCache')) {
                $model->clearCache();
            }
        });

        // @phpstan-ignore-next-line
        if (method_exists(static::class, 'restored')) {
            // @phpstan-ignore-next-line
            static::restored(function (Model $model): void {
                if (method_exists($model, 'handleCacheRefresh')) {
                    $model->handleCacheRefresh();
                }
            });
        }
        static::updated(function (Model $model): void {

            if (method_exists($model, 'handleCacheRefresh')) {
                $model->handleCacheRefresh();
            }
        });

        static::created(function (Model $model): void {
            if (method_exists($model, 'handleCacheRefresh')) {
                $model->handleCacheRefresh();
            }
        });
        // @phpstan-ignore-next-line
        if (method_exists(static::class, 'relationsUpdated')) {
            static::relationsUpdated(function (Model $model): void {
                if (method_exists($model, 'handleCacheRefresh')) {
                    $model->handleCacheRefresh();
                }
            });
        }
        // @phpstan-ignore-next-line
        if (method_exists(static::class, 'pivotUpdated')) {
            static::pivotUpdated(function (Model $model): void {
                if (method_exists($model, 'handleCacheRefresh')) {
                    $model->handleCacheRefresh();
                }
            });
        }
    }

    /**
     * Handle cache refresh after model changes.
     */
    public function handleCacheRefresh(): void
    {

        $this->clearCache();
    }

    /**
     * Clear the cache for this model instance.
     */
    public function clearCache(): void
    {
        $cacheKey = $this->getCacheKey();
        $cacheTag = $this->getCacheTag();
        $cacheTagAll = $this->getCacheTag('', 'all');

        // Clear the specific cache key first
        Cache::forget($cacheKey);

        // Clear tags separately to avoid CROSSSLOT errors in Redis cluster
        // Each tag flush is a separate operation
        try {
            Cache::tags($cacheTag)->flush();
        } catch (Exception $e) {
            // Log but don't fail if individual tag flush fails
            Log::warning('[Cachable::clearCache] Failed to flush cache tag: '.$cacheTag, [
                'error' => $e->getMessage(),
                'environment' => app()->environment(),
            ]);
        }

        try {
            Cache::tags($cacheTagAll)->flush();
        } catch (Exception $e) {
            // Log but don't fail if individual tag flush fails
            Log::warning('[Cachable::clearCache] Failed to flush cache tag: '.$cacheTagAll, [
                'error' => $e->getMessage(),
                'environment' => app()->environment(),
            ]);
        }

    }

    /**
     * Get the cache key for this model instance.
     *
     * @param  string|null  $suffix  Optional suffix to append to the key
     */
    public function getCacheKey(?string $suffix = null): string
    {
        return cache_key_from_model(
            $this,
            (Auth::check() && Auth::id() !== null ? Auth::id() : '').'_'.$suffix
        );
    }

    /**
     * Get the cache tag for this model.
     */
    public function getCacheTag(string $id = '', ?string $type = null): string
    {
        if ($id === '' && $type === null) {
            $key = $this->getKey();
            $id = is_int($key) || is_string($key) ? $key : '';
        }
        // @phpstan-ignore-next-line
        $castedId = is_int($id) || is_string($id) ? $id : null;

        return cache_tag(class_basename($this), $castedId);
    }
}
