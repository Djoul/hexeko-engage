<?php

namespace App\Traits;

use DateTimeInterface;
use Illuminate\Support\Facades\Storage;

trait HasTemporaryMediaUrls
{
    /**
     * Get a temporary URL for a media item
     */
    public function getTemporaryMediaUrl(string $collectionName = 'default', ?DateTimeInterface $expiration = null): ?string
    {
        $media = $this->getFirstMedia($collectionName);

        if (! $media) {
            return null;
        }

        // Default expiration is 1 hour
        $expiration = $expiration ?? now()->addHour();

        // Get the path relative to the disk root
        $path = $media->getPath();

        // Extract just the path portion after the disk root
        $diskRoot = Storage::disk($media->disk)->path('');
        $relativePath = str_replace($diskRoot, '', $path);

        // For S3, we need the path without the local storage prefix
        if (in_array($media->disk, ['s3', 's3-local'])) {
            $relativePath = $media->id.'/'.$media->file_name;
        }

        return Storage::disk($media->disk)->temporaryUrl(
            ltrim($relativePath, '/'),
            $expiration
        );
    }

    /**
     * Get temporary URLs for all media in a collection
     */
    public function getTemporaryMediaUrls(string $collectionName = 'default', ?DateTimeInterface $expiration = null): array
    {
        $mediaItems = $this->getMedia($collectionName);
        $urls = [];

        $expiration = $expiration ?? now()->addHour();

        foreach ($mediaItems as $media) {
            $relativePath = $media->disk === 's3'
                ? $media->id.'/'.$media->file_name
                : str_replace(Storage::disk($media->disk)->path(''), '', $media->getPath());

            $urls[] = [
                'id' => $media->id,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'url' => Storage::disk($media->disk)->temporaryUrl(
                    ltrim($relativePath, '/'),
                    $expiration
                ),
            ];
        }

        return $urls;
    }
}
