<?php

namespace App\Integrations\HRTools\Traits;

use App\Enums\Languages;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait LinkAccessorsAndHelpers
{
    private const DEFAULT_LOGO_URL = 'https://images.seeklogo.com/logo-png/52/1/pollective-logo-png_seeklogo-520330.png';

    protected static function logName(): string
    {
        return 'Link';
    }

    /**
     * @return Attribute<string, never>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            fn (): string => $this->resolveLogoUrl(),
            null
        );
    }

    private function resolveLogoUrl(): string
    {
        if ($this->hasMedia('logo')) {
            return $this->getFirstMediaUrl('logo');
        }

        $attributeLogoUrl = $this->attributes['logo_url'] ?? null;

        return is_string($attributeLogoUrl) ? $attributeLogoUrl : self::DEFAULT_LOGO_URL;
    }

    /**
     * Get the available languages for this link.
     * Uses the financer's available languages if set, otherwise falls back to all Languages enum values.
     *
     * @return array<int, int|string>
     */
    public function getAvailableLanguages(): array
    {
        if ($this->financer && ! empty($this->financer->available_languages)) {
            return $this->financer->available_languages;
        }

        return Languages::getValues();
    }
}
