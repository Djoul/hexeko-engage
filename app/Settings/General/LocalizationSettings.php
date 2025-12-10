<?php

namespace App\Settings\General;

use Spatie\LaravelSettings\Settings;

class LocalizationSettings extends Settings
{
    public string $default_locale;

    /**
     * @var array<int, string>
     */
    public array $available_locales;

    public string $fallback_locale;

    public static function group(): string
    {
        return 'localization';
    }
}
