<?php

namespace Tests\Unit\Settings;

use App\Enums\Languages;
use App\Settings\General\LocalizationSettings;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('settings')]
class LocalizationSettingsTest extends TestCase
{
    #[Test]
    public function it_has_correct_group(): void
    {
        $this->assertEquals('localization', LocalizationSettings::group());
    }

    #[Test]
    public function it_has_default_values_with_languages_enum(): void
    {
        LocalizationSettings::fake([
            'default_locale' => Languages::FRENCH,
            'available_locales' => [Languages::FRENCH, Languages::DUTCH_BELGIUM, Languages::ENGLISH],
            'fallback_locale' => Languages::ENGLISH,
        ]);

        $settings = app(LocalizationSettings::class);

        $this->assertEquals('fr-FR', $settings->default_locale);
        $this->assertEquals(['fr-FR', 'nl-BE', 'en-GB'], $settings->available_locales);
        $this->assertEquals('en-GB', $settings->fallback_locale);
    }

    #[Test]
    public function it_can_update_available_locales(): void
    {
        LocalizationSettings::fake([
            'default_locale' => Languages::FRENCH,
            'available_locales' => [Languages::FRENCH, Languages::ENGLISH],
            'fallback_locale' => Languages::ENGLISH,
        ]);

        $settings = app(LocalizationSettings::class);

        // Ajouter le néerlandais belge et l'allemand
        $settings->available_locales = [
            Languages::FRENCH,
            Languages::ENGLISH,
            Languages::DUTCH_BELGIUM,
            Languages::GERMAN,
        ];

        $this->assertContains('nl-BE', $settings->available_locales);
        $this->assertContains('de-DE', $settings->available_locales);
        $this->assertCount(4, $settings->available_locales);
    }

    #[Test]
    public function it_validates_default_locale_is_in_available_locales(): void
    {
        LocalizationSettings::fake([
            'default_locale' => Languages::FRENCH,
            'available_locales' => [Languages::FRENCH, Languages::ENGLISH, Languages::DUTCH_BELGIUM],
            'fallback_locale' => Languages::ENGLISH,
        ]);

        $settings = app(LocalizationSettings::class);

        $this->assertContains($settings->default_locale, $settings->available_locales);
    }

    #[Test]
    public function it_respects_type_declarations(): void
    {
        LocalizationSettings::fake([
            'default_locale' => Languages::FRENCH,
            'available_locales' => [Languages::FRENCH, Languages::ENGLISH],
            'fallback_locale' => Languages::ENGLISH,
        ]);

        $settings = app(LocalizationSettings::class);

        $this->assertIsString($settings->default_locale);
        $this->assertIsArray($settings->available_locales);
        $this->assertIsString($settings->fallback_locale);

        // Vérifier que tous les éléments du tableau sont des strings
        foreach ($settings->available_locales as $locale) {
            $this->assertIsString($locale);
        }
    }

    #[Test]
    public function it_can_change_default_locale(): void
    {
        LocalizationSettings::fake([
            'default_locale' => Languages::FRENCH,
            'available_locales' => [Languages::FRENCH, Languages::ENGLISH, Languages::DUTCH_BELGIUM],
            'fallback_locale' => Languages::ENGLISH,
        ]);

        $settings = app(LocalizationSettings::class);

        $this->assertEquals('fr-FR', $settings->default_locale);

        $settings->default_locale = Languages::DUTCH_BELGIUM;

        $this->assertEquals('nl-BE', $settings->default_locale);
    }
}
