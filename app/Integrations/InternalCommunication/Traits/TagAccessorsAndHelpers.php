<?php

namespace App\Integrations\InternalCommunication\Traits;

use Illuminate\Support\Arr;

trait TagAccessorsAndHelpers
{
    /**
     * Get the translated label for the tag.
     */
    public function getTranslatedLabel(?string $locale = null): ?string
    {
        if (in_array($locale, [null, '', '0'], true)) {
            $locale = app()->getLocale();
        }

        // Get the raw label value from the database
        $rawLabel = $this->getRawOriginal('label');
        if (is_string($rawLabel)) {
            $decodedLabel = json_decode($rawLabel, true);

            if (is_array($decodedLabel)) {
                $value = Arr::get($decodedLabel, $locale);
                if ($value !== null) {
                    return is_scalar($value) ? (string) $value : null;
                }

                return null;
            }

            return null;
        }

        $label = $this->label;
        if (is_array($label)) {
            $value = Arr::get($label, $locale);
            if ($value !== null) {
                return is_scalar($value) ? (string) $value : null;
            }

            return null;
        }

        return null;
    }

    /**
     * Check if the tag is used in any article.
     */
    public function isUsed(): bool
    {
        return $this->articles()->exists();
    }

    /**
     * Get the count of articles using this tag.
     */
    public function getArticleCount(): int
    {
        return $this->articles()->count();
    }

    /**
     * Check if the tag belongs to the given financer.
     */
    public function belongsToFinancer(string $financerId): bool
    {
        return $this->financer_id === $financerId;
    }
}
