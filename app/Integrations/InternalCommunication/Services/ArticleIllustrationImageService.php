<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Services;

use App\Integrations\InternalCommunication\Models\Article;
use App\Services\Media\HeicImageConversionService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Class IllustrationImageService
 *
 * Dedicated service for handling article illustrations
 */
class ArticleIllustrationImageService
{
    public function __construct(
        private readonly HeicImageConversionService $heicConversionService
    ) {}

    /**
     * Update article illustration
     *
     * @param  Article  $article  The article to update
     * @param  string  $illustration  The base64 encoded image or URL
     */
    public function updateIllustration(Article $article, string $illustration): void
    {
        try {
            // Force reload of media relationship to ensure we have the latest data
            $article->load('media');

            // Get all current illustrations
            $oldIllustrations = $article->getMedia('illustration');

            // Deactivate all old illustrations (keep them for history)
            if ($oldIllustrations->isNotEmpty()) {
                Log::info('Deactivating old illustrations', [
                    'article_id' => $article->id,
                    'count' => $oldIllustrations->count(),
                    'media_ids' => $oldIllustrations->pluck('id')->toArray(),
                ]);

                foreach ($oldIllustrations as $oldMedia) {
                    // Update custom properties to set active to false
                    $customProperties = $oldMedia->custom_properties ?? [];
                    $customProperties['active'] = false;
                    $oldMedia->custom_properties = $customProperties;
                    $oldMedia->save();

                    Log::debug('Deactivated media', [
                        'media_id' => $oldMedia->id,
                        'path' => $oldMedia->getPath(),
                    ]);
                }
            }

            // Convert HEIC to JPG if needed
            $processedIllustration = $this->heicConversionService->processImage($illustration);

            // Add the new illustration
            $media = $article->addMediaFromBase64($processedIllustration)
                ->withCustomProperties(['active' => true])
                ->usingFileName($this->generateFileName($processedIllustration))
                ->toMediaCollection('illustration');

            Log::info('Article illustration updated', [
                'article_id' => $article->id,
                'media_id' => $media->id,
                'collection' => 'illustration',
                'path' => $media->getPath(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to update article illustration', [
                'article_id' => $article->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate a unique filename for the illustration
     */
    protected function generateFileName(string $base64): string
    {
        // Extract mime type from base64 string
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
            $extension = $matches[1];
        } else {
            $extension = 'png'; // Default extension
        }

        return 'illustration_'.uniqid().'.'.$extension;
    }
}
