<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;

if (! function_exists('cache_key')) {
    /**
     * Generate a consistent cache key.
     *
     * @param  string  $prefix  The model or entity name
     * @param  int|string|null  $id  Optional identifier
     * @param  string|null  $suffix  Optional suffix
     * @return string The formatted cache key
     */
    function cache_key(string $prefix, int|string|null $id = null, ?string $suffix = null): string
    {
        $key = strtolower($prefix);

        if ($id !== null) {
            $key .= "_{$id}";
        }

        if ($suffix !== null) {
            $key .= "_{$suffix}";
        }
        $jsonEncode = json_encode(request()->all());
        $key .= md5($jsonEncode !== false ? $jsonEncode : '');

        $financerId = activeFinancerID();
        if (is_string($financerId)) {
            $key .= $financerId;
        } elseif (is_array($financerId)) {
            $key .= implode('_', $financerId);
        }

        // Add hash tag for Redis Cluster to ensure related keys are on the same slot
        if (config('database.redis.options.cluster') === 'redis') {
            $hashTag = $id !== null ? "{entity_{$prefix}_{$id}}" : "{entity_{$prefix}}";
            $key = $hashTag.'_'.$key;
        }

        return $key;
    }
}

if (! function_exists('cache_tag')) {
    /**
     * Generate a cache tag for a specific entity.
     *
     * @param  string  $prefix  The model or entity name
     * @param  int|string  $id  The entity identifier
     * @return string The formatted cache tag
     */
    function cache_tag(string $prefix, int|string $id): string
    {
        //        $userId = Auth::check() ? Auth::id() : '';

        $tag = strtolower("{$prefix}_{$id}");

        // Add hash tag for Redis Cluster to ensure related keys are on the same slot
        if (config('database.redis.options.cluster') === 'redis') {
            return "{entity_{$prefix}_{$id}}".'_'.$tag;
        }

        return $tag;
    }
}

if (! function_exists('cache_key_from_model')) {
    /**
     * Generate a cache key from a model instance.
     *
     * @param  Model  $model  The model instance
     * @param  string|null  $suffix  Optional suffix
     * @return string The formatted cache key
     */
    function cache_key_from_model(Model $model, ?string $suffix = null): string
    {
        // getKey() can return mixed, but we want int|string|null
        $id = $model->getKey();
        if ($id !== null && ! is_int($id) && ! is_string($id)) {
            $id = null;
        }

        return cache_key(
            class_basename($model),
            $id,
            $suffix
        );
    }
}

if (! function_exists('refreshModelCache')) {
    /**
     * Refresh cache for a given Eloquent model class or instance.
     */
    function refreshModelCache(string|Model $modelClassOrInstance): void
    {
        $model = is_string($modelClassOrInstance)
            ? new $modelClassOrInstance
            : $modelClassOrInstance;

        if (method_exists($model, 'handleCacheRefresh')) {
            $model->handleCacheRefresh();
        }
    }
}
