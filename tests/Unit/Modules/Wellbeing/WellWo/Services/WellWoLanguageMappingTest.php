<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Wellbeing\WellWo\Services;

use App\Enums\Languages;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('wellwo')]
class WellWoLanguageMappingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up the language mapping in config
        Config::set('services.wellwo.language_mapping', [
            Languages::SPANISH => 'es',
            Languages::SPANISH_ARGENTINA => 'es',
            Languages::SPANISH_COLOMBIA => 'es',
            Languages::SPANISH_MEXICO => 'mx',
            Languages::ENGLISH => 'en',
            Languages::FRENCH => 'fr',
            Languages::FRENCH_BELGIUM => 'fr',
            Languages::FRENCH_CANADA => 'fr',
            Languages::FRENCH_SWITZERLAND => 'fr',
            Languages::ITALIAN => 'it',
            Languages::PORTUGUESE => 'pt',
            Languages::PORTUGUESE_BRAZIL => 'pt',
        ]);

        Config::set('services.wellwo.default_language', 'en');
        Config::set('services.wellwo.supported_languages', ['es', 'en', 'fr', 'it', 'pt', 'ca', 'mx']);
    }

    #[Test]
    public function it_maps_spanish_locales_to_es(): void
    {
        $mapping = config('services.wellwo.language_mapping');

        $this->assertEquals('es', $mapping[Languages::SPANISH]);
        $this->assertEquals('es', $mapping[Languages::SPANISH_ARGENTINA]);
        $this->assertEquals('es', $mapping[Languages::SPANISH_COLOMBIA]);
    }

    #[Test]
    public function it_maps_mexican_spanish_to_mx(): void
    {
        $mapping = config('services.wellwo.language_mapping');
        $this->assertEquals('mx', $mapping[Languages::SPANISH_MEXICO]);
    }

    #[Test]
    public function it_maps_english_locales_to_en(): void
    {
        $mapping = config('services.wellwo.language_mapping');
        $this->assertEquals('en', $mapping[Languages::ENGLISH]);
    }

    #[Test]
    public function it_maps_french_locales_to_fr(): void
    {
        $mapping = config('services.wellwo.language_mapping');

        $this->assertEquals('fr', $mapping[Languages::FRENCH]);
        $this->assertEquals('fr', $mapping[Languages::FRENCH_BELGIUM]);
        $this->assertEquals('fr', $mapping[Languages::FRENCH_CANADA]);
        $this->assertEquals('fr', $mapping[Languages::FRENCH_SWITZERLAND]);
    }

    #[Test]
    public function it_maps_italian_locale_to_it(): void
    {
        $mapping = config('services.wellwo.language_mapping');
        $this->assertEquals('it', $mapping[Languages::ITALIAN]);
    }

    #[Test]
    public function it_maps_portuguese_locales_to_pt(): void
    {
        $mapping = config('services.wellwo.language_mapping');

        $this->assertEquals('pt', $mapping[Languages::PORTUGUESE]);
        $this->assertEquals('pt', $mapping[Languages::PORTUGUESE_BRAZIL]);
    }

    #[Test]
    public function it_has_default_language_configured(): void
    {
        $default = config('services.wellwo.default_language');
        $this->assertEquals('en', $default);
    }

    #[Test]
    public function it_has_supported_languages_configured(): void
    {
        $supported = config('services.wellwo.supported_languages');

        $this->assertIsArray($supported);
        $this->assertContains('es', $supported);
        $this->assertContains('en', $supported);
        $this->assertContains('fr', $supported);
        $this->assertContains('it', $supported);
        $this->assertContains('pt', $supported);
        $this->assertContains('ca', $supported);
        $this->assertContains('mx', $supported);
    }

    #[Test]
    #[DataProvider('localeProvider')]
    public function it_maps_all_locales_correctly(string $locale, string $expected): void
    {
        $mapping = config('services.wellwo.language_mapping');
        $this->assertEquals($expected, $mapping[$locale] ?? config('services.wellwo.default_language'));
    }

    public static function localeProvider(): array
    {
        return [
            'Spanish Spain' => [Languages::SPANISH, 'es'],
            'Spanish Argentina' => [Languages::SPANISH_ARGENTINA, 'es'],
            'Spanish Colombia' => [Languages::SPANISH_COLOMBIA, 'es'],
            'Spanish Mexico' => [Languages::SPANISH_MEXICO, 'mx'],
            'English GB' => [Languages::ENGLISH, 'en'],
            'French France' => [Languages::FRENCH, 'fr'],
            'French Belgium' => [Languages::FRENCH_BELGIUM, 'fr'],
            'French Canada' => [Languages::FRENCH_CANADA, 'fr'],
            'French Switzerland' => [Languages::FRENCH_SWITZERLAND, 'fr'],
            'Italian' => [Languages::ITALIAN, 'it'],
            'Portuguese Portugal' => [Languages::PORTUGUESE, 'pt'],
            'Portuguese Brazil' => [Languages::PORTUGUESE_BRAZIL, 'pt'],
        ];
    }
}
