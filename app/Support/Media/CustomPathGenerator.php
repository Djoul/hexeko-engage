<?php

namespace App\Support\Media;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class CustomPathGenerator implements PathGenerator
{
    /**
     * Get the path for the given media, relative to the root storage path.
     * Format: {modelName}/{modelId}/{collectionName}/{mediaId}/
     */
    public function getPath(Media $media): string
    {
        $modelName = $this->getModelName($media);
        $modelId = $media->model_id;
        $collectionName = $media->collection_name;
        $mediaId = $media->id;

        return $this->formatPath($modelName, $modelId, $collectionName, $mediaId);
    }

    /**
     * Get the path for conversions of the given media, relative to the root storage path.
     * Format: {modelName}/{modelId}/{mediaId}/conversions/
     */
    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media).'conversions/';
    }

    /**
     * Get the path for responsive images of the given media, relative to the root storage path.
     * Format: {modelName}/{modelId}/{mediaId}/responsive/
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media).'responsive/';
    }

    /**
     * Get a unique base path for the given media.
     * This is used when moving/copying media to ensure uniqueness.
     */
    protected function getBasePath(Media $media): string
    {
        $modelName = $this->getModelName($media);
        $modelId = $media->model_id;
        $collectionName = $media->collection_name;
        $mediaId = $media->id;

        return $this->formatPath($modelName, $modelId, $collectionName, $mediaId);
    }

    /**
     * Extract clean model name from the model class.
     * Removes namespace and converts to lowercase plural form.
     */
    protected function getModelName(Media $media): string
    {
        $modelClass = $media->model_type;

        // Handle morphMap if it exists
        $morphMap = Relation::morphMap();
        if ($morphMap) {
            $modelClass = array_search($modelClass, $morphMap) ?: $modelClass;
        }

        // Extract the class name without namespace
        $className = class_basename($modelClass);

        // Convert to lowercase and pluralize
        $modelName = strtolower(Str::plural($className));

        return $modelName;
    }

    /**
     * Format the path with the given components.
     */
    protected function formatPath(string $modelName, int|string $modelId, string $collectionName, int|string $mediaId): string
    {
        $prefix = config('media-library.prefix', '');

        if ($prefix !== '') {
            return "{$prefix}/{$modelName}/{$modelId}/{$collectionName}/{$mediaId}/";
        }

        return "{$modelName}/{$modelId}/{$collectionName}/{$mediaId}/";
    }
}
