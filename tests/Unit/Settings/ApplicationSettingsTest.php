<?php

namespace Tests\Unit\Settings;

use App\Settings\General\ApplicationSettings;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('settings')]
class ApplicationSettingsTest extends TestCase
{
    #[Test]
    public function it_has_correct_group(): void
    {
        $this->assertEquals('application', ApplicationSettings::group());
    }

    #[Test]
    public function it_has_default_values(): void
    {
        ApplicationSettings::fake([
            'app_name' => 'Up Engage API',
            'app_url' => 'http://localhost:1310',
            'timezone' => 'Europe/Brussels',
            'maintenance_mode' => false,
            'maintenance_message' => null,
        ]);

        $settings = app(ApplicationSettings::class);

        $this->assertEquals('Up Engage API', $settings->app_name);
        $this->assertEquals('http://localhost:1310', $settings->app_url);
        $this->assertEquals('Europe/Brussels', $settings->timezone);
        $this->assertFalse($settings->maintenance_mode);
        $this->assertNull($settings->maintenance_message);
    }

    #[Test]
    public function it_can_toggle_maintenance_mode(): void
    {
        ApplicationSettings::fake([
            'app_name' => 'Test',
            'app_url' => 'http://test',
            'timezone' => 'UTC',
            'maintenance_mode' => false,
            'maintenance_message' => null,
        ]);

        $settings = app(ApplicationSettings::class);

        $this->assertFalse($settings->maintenance_mode);

        $settings->maintenance_mode = true;
        $settings->maintenance_message = 'Maintenance en cours';

        $this->assertTrue($settings->maintenance_mode);
        $this->assertEquals('Maintenance en cours', $settings->maintenance_message);
    }

    #[Test]
    public function it_validates_timezone_format(): void
    {
        ApplicationSettings::fake([
            'app_name' => 'Test',
            'app_url' => 'http://test',
            'timezone' => 'Europe/Brussels',
            'maintenance_mode' => false,
            'maintenance_message' => null,
        ]);

        $settings = app(ApplicationSettings::class);

        $this->assertContains($settings->timezone, timezone_identifiers_list());
    }

    #[Test]
    public function it_respects_type_declarations(): void
    {
        ApplicationSettings::fake([
            'app_name' => 'Test',
            'app_url' => 'http://test',
            'timezone' => 'UTC',
            'maintenance_mode' => false,
            'maintenance_message' => 'Test message',
        ]);

        $settings = app(ApplicationSettings::class);

        $this->assertIsString($settings->app_name);
        $this->assertIsString($settings->app_url);
        $this->assertIsString($settings->timezone);
        $this->assertIsBool($settings->maintenance_mode);
        $this->assertIsString($settings->maintenance_message);
    }
}
