<?php

namespace App\Settings\General;

use Spatie\LaravelSettings\Settings;

class ApplicationSettings extends Settings
{
    public string $app_name;

    public string $app_url;

    public string $timezone;

    public bool $maintenance_mode;

    public ?string $maintenance_message;

    public static function group(): string
    {
        return 'application';
    }
}
