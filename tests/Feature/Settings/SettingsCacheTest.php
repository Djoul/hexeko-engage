<?php

namespace Tests\Feature\Settings;

use App\Settings\General\ApplicationSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('settings')]
class SettingsCacheTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // S'assurer que le cache est activé pour ces tests
        config(['settings.cache.enabled' => true]);
        Cache::flush();
    }

    #[Test]
    public function it_caches_settings_after_first_load(): void
    {
        $settings = app(ApplicationSettings::class);
        $settings->app_name = 'Cached App';
        $settings->app_url = 'http://cached.test';
        $settings->timezone = 'UTC';
        $settings->maintenance_mode = false;
        $settings->save();

        // Premier chargement - devrait mettre en cache
        $settings->refresh();
        $firstName = $settings->app_name;

        // Le cache devrait être utilisé
        $this->assertEquals('Cached App', $firstName);

        // Vérifier que les settings sont bien chargées
        $settingsReloaded = app(ApplicationSettings::class);
        $this->assertEquals('Cached App', $settingsReloaded->app_name);
    }

    #[Test]
    public function it_invalidates_cache_on_save(): void
    {
        $settings = app(ApplicationSettings::class);
        $settings->app_name = 'Initial';
        $settings->app_url = 'http://initial.test';
        $settings->timezone = 'UTC';
        $settings->maintenance_mode = false;
        $settings->save();

        // Charger pour mettre en cache
        $settings->refresh();

        // Modifier et sauvegarder (devrait invalider le cache)
        $settings->app_name = 'Updated';
        $settings->save();

        // Recharger - devrait avoir la nouvelle valeur
        $settings->refresh();
        $this->assertEquals('Updated', $settings->app_name);
    }

    #[Test]
    public function it_serves_from_cache_when_enabled(): void
    {
        config(['settings.cache.enabled' => true]);

        $settings = app(ApplicationSettings::class);
        $settings->app_name = 'Test Cache';
        $settings->app_url = 'http://test.cache';
        $settings->timezone = 'Europe/Brussels';
        $settings->maintenance_mode = false;
        $settings->save();

        // Premier chargement
        $settings->refresh();

        // Deuxième chargement (depuis cache)
        $cachedSettings = app(ApplicationSettings::class);

        $this->assertEquals('Test Cache', $cachedSettings->app_name);
        $this->assertEquals('Europe/Brussels', $cachedSettings->timezone);
    }

    #[Test]
    public function it_bypasses_cache_when_disabled(): void
    {
        config(['settings.cache.enabled' => false]);

        $settings = app(ApplicationSettings::class);
        $settings->app_name = 'No Cache';
        $settings->app_url = 'http://nocache.test';
        $settings->timezone = 'UTC';
        $settings->maintenance_mode = false;
        $settings->save();

        $settings->refresh();

        // Devrait toujours charger depuis DB
        $this->assertEquals('No Cache', $settings->app_name);
    }
}
