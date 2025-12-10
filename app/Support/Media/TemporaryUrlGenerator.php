<?php

namespace App\Support\Media;

use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\UrlGenerator\BaseUrlGenerator;

class TemporaryUrlGenerator extends BaseUrlGenerator
{
    public function getUrl(): string
    {
        if (! $this->media instanceof Media) {
            return '';
        }

        $path = $this->getPathRelativeToRoot();

        // For S3 disks, generate a temporary URL valid for 1 hour
        if (in_array($this->media->disk, ['s3', 's3-local'])) {
            return Storage::disk($this->media->disk)
                ->temporaryUrl($path, now()->addHour());
        }

        // For local disk, use the default URL
        return Storage::disk($this->media->disk)->url($path);
    }

    public function getPath(): string
    {
        if (! $this->media instanceof Media) {
            return '';
        }

        // Use our custom path generator format
        $pathGenerator = new CustomPathGenerator;

        return $pathGenerator->getPath($this->media).$this->media->file_name;
    }

    public function getTemporaryUrl(DateTimeInterface $expiration, array $options = []): string
    {
        if (! $this->media instanceof Media) {
            return '';
        }

        $path = $this->getPathRelativeToRoot();

        return Storage::disk($this->media->disk)
            ->temporaryUrl($path, $expiration, $options);
    }

    public function getResponsiveImagesDirectoryUrl(): string
    {
        if (! $this->media instanceof Media) {
            return '';
        }

        $this->getPathRelativeToRoot();
        $responsiveImagesDirectory = $this->pathGenerator->getPathForResponsiveImages($this->media);

        if (in_array($this->media->disk, ['s3', 's3-local'])) {
            return Storage::disk($this->media->disk)
                ->temporaryUrl($responsiveImagesDirectory, now()->addHour());
        }

        return Storage::disk($this->media->disk)->url($responsiveImagesDirectory);
    }
}
