<?php

use App\Enums\Languages;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('localization.default_locale', Languages::ENGLISH);

        $this->migrator->add('localization.available_locales', [
            Languages::FRENCH,
            Languages::FRENCH_BELGIUM,
            Languages::DUTCH_BELGIUM,
            Languages::ENGLISH,
            Languages::PORTUGUESE,
        ]);
        $this->migrator->add('localization.fallback_locale', Languages::ENGLISH);
    }
};
