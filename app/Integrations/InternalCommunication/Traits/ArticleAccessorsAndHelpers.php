<?php

namespace App\Integrations\InternalCommunication\Traits;

use App;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait ArticleAccessorsAndHelpers
{
    /**
     * Get the name to use when logging model events.
     */
    protected static function logName(): string
    {
        return 'article';
    }

    /**
     * Get the translation for a specific language (default: app locale).
     */
    public function translation(?string $lang = null): ?ArticleTranslation
    {
        // Check if translations are already loaded to avoid N+1 queries
        if ($this->relationLoaded('translations')) {
            $translations = $this->translations;

            if ($translations->count() === 1) {
                $first = $translations->first();

                return $first instanceof ArticleTranslation ? $first : null;
            }

            $result = $translations->firstWhere('language', $lang ?? App::currentLocale());

            return $result instanceof ArticleTranslation ? $result : null;
        }

        // If not loaded, use the query builder to avoid loading all translations
        $targetLang = $lang ?? App::currentLocale();
        $result = $this->translations()->where('language', $targetLang)->first();

        if ($result instanceof ArticleTranslation) {
            return $result;
        }

        // If no translation found for the target language, return the first one
        /** @var ArticleTranslation|null */
        return $this->translations()->first();
    }

    /**
     * Get the active illustration media.
     *
     * @return Media|null
     */
    public function getActiveIllustration()
    {
        // Check if media relation is already loaded to avoid N+1 queries
        if ($this->relationLoaded('media')) {
            return $this->media
                ->where('collection_name', 'illustration')
                ->where('custom_properties.active', true)
                ->first();
        }

        // If not loaded, use query to get only the active illustration
        return $this->getMedia('illustration')
            ->where('custom_properties.active', true)
            ->first();
    }

    /**
     * Get the active illustration URL.
     */
    public function activeIllustrationUrl(): Attribute
    {
        return Attribute::make(
            function () {
                $activeMedia = $this->getActiveIllustration();

                if (! $activeMedia) {
                    return '';
                }

                // Generate temporary URL for S3 media
                if (in_array($activeMedia->disk, ['s3', 's3-local'])) {
                    return $activeMedia->getTemporaryUrl(now()->addHour());
                }

                return $activeMedia->getUrl();
            }
        );
    }
}
