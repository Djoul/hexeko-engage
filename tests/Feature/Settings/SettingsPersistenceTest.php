<?php

namespace Tests\Feature\Settings;

use App\Enums\Languages;
use App\Settings\General\ApplicationSettings;
use App\Settings\General\LocalizationSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('settings')]
class SettingsPersistenceTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_persists_application_settings_to_database(): void
    {
        $settings = app(ApplicationSettings::class);
        $settings->app_name = 'Up Engage API';
        $settings->app_url = 'https://api.upengageapp.com';
        $settings->timezone = 'Europe/Brussels';
        $settings->maintenance_mode = false;
        $settings->maintenance_message = null;
        $settings->save();

        // Vérifier dans la table settings
        $this->assertDatabaseHas('settings', [
            'group' => 'application',
            'name' => 'app_name',
        ]);

        $this->assertDatabaseHas('settings', [
            'group' => 'application',
            'name' => 'timezone',
        ]);

        // Recharger depuis DB
        $settings->refresh();
        $this->assertEquals('Up Engage API', $settings->app_name);
        $this->assertEquals('Europe/Brussels', $settings->timezone);
    }

    #[Test]
    public function it_persists_localization_settings_to_database(): void
    {
        $settings = app(LocalizationSettings::class);
        $settings->default_locale = Languages::FRENCH;
        $settings->available_locales = [Languages::FRENCH, Languages::DUTCH_BELGIUM, Languages::ENGLISH];
        $settings->fallback_locale = Languages::ENGLISH;
        $settings->save();

        // Vérifier dans la table settings
        $this->assertDatabaseHas('settings', [
            'group' => 'localization',
            'name' => 'default_locale',
        ]);

        // Recharger depuis DB
        $settings->refresh();
        $this->assertEquals('fr-FR', $settings->default_locale);
        $this->assertIsArray($settings->available_locales);
        $this->assertCount(3, $settings->available_locales);
    }

    #[Test]
    public function it_updates_existing_settings(): void
    {
        $settings = app(ApplicationSettings::class);
        $settings->app_name = 'Initial Name';
        $settings->app_url = 'http://initial.test';
        $settings->timezone = 'UTC';
        $settings->maintenance_mode = false;
        $settings->save();

        // Update
        $settings->app_name = 'Updated Name';
        $settings->timezone = 'Europe/Brussels';
        $settings->save();

        $settings->refresh();
        $this->assertEquals('Updated Name', $settings->app_name);
        $this->assertEquals('Europe/Brussels', $settings->timezone);
    }

    #[Test]
    public function it_handles_nullable_properties_in_database(): void
    {
        $settings = app(ApplicationSettings::class);
        $settings->app_name = 'Test';
        $settings->app_url = 'http://test';
        $settings->timezone = 'UTC';
        $settings->maintenance_mode = false;
        $settings->maintenance_message = 'Maintenance en cours';
        $settings->save();

        $settings->refresh();
        $this->assertEquals('Maintenance en cours', $settings->maintenance_message);

        // Set to null
        $settings->maintenance_message = null;
        $settings->save();

        $settings->refresh();
        $this->assertNull($settings->maintenance_message);
    }

    #[Test]
    public function it_persists_array_properties_correctly(): void
    {
        $settings = app(LocalizationSettings::class);
        $locales = [Languages::FRENCH, Languages::ENGLISH, Languages::GERMAN, Languages::DUTCH_BELGIUM];
        $settings->default_locale = Languages::FRENCH;
        $settings->available_locales = $locales;
        $settings->fallback_locale = Languages::ENGLISH;
        $settings->save();

        $settings->refresh();

        $this->assertIsArray($settings->available_locales);
        $this->assertCount(4, $settings->available_locales);
        $this->assertContains('fr-FR', $settings->available_locales);
        $this->assertContains('de-DE', $settings->available_locales);
    }
}
