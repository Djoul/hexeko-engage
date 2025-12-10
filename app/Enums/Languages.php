<?php

namespace App\Enums;

use App\Settings\General\LocalizationSettings;
use InvalidArgumentException;

final class Languages extends BaseEnum
{
    const DUTCH = 'nl-NL';

    const ENGLISH = 'en-GB';

    const FRENCH = 'fr-FR';

    const GERMAN = 'de-DE';

    const ITALIAN = 'it-IT';

    const POLISH = 'pl-PL';

    const PORTUGUESE = 'pt-PT';

    const ROMANIAN = 'ro-RO';

    const RUSSIAN = 'ru-RU';

    const SPANISH = 'es-ES';

    const UKRAINIAN = 'uk-UA';

    // Regional variants
    const FRENCH_BELGIUM = 'fr-BE';

    const DUTCH_BELGIUM = 'nl-BE';

    const FRENCH_CANADA = 'fr-CA';

    const FRENCH_SWITZERLAND = 'fr-CH';

    const GERMAN_AUSTRIA = 'de-AT';

    const GERMAN_SWITZERLAND = 'de-CH';

    const PORTUGUESE_BRAZIL = 'pt-BR';

    const SPANISH_ARGENTINA = 'es-AR';

    const SPANISH_COLOMBIA = 'es-CO';

    const SPANISH_MEXICO = 'es-MX';

    /**
     * Get the native name of the language.
     *
     * @param  string  $key
     */
    public static function nativeName($key): string
    {
        return match ($key) {
            self::DUTCH => 'Nederlands',
            self::DUTCH_BELGIUM => 'Nederlands (Belgïe)',
            self::ENGLISH => 'English',
            self::FRENCH => 'Français',
            self::FRENCH_BELGIUM => 'Français (Belgique)',
            self::FRENCH_CANADA => 'Français (Canada)',
            self::FRENCH_SWITZERLAND => 'Français (Suisse)',
            self::GERMAN => 'Deutsch',
            self::GERMAN_AUSTRIA => 'Deutsch (Österreich)',
            self::GERMAN_SWITZERLAND => 'Deutsch (Schweiz)',
            self::ITALIAN => 'Italiano',
            self::POLISH => 'Polski',
            self::PORTUGUESE => 'Português',
            self::PORTUGUESE_BRAZIL => 'Português (Brasil)',
            self::ROMANIAN => 'Română',
            self::RUSSIAN => 'Русский',
            self::SPANISH => 'Español',
            self::SPANISH_ARGENTINA => 'Español (Argentina)',
            self::SPANISH_COLOMBIA => 'Español (Colombia)',
            self::SPANISH_MEXICO => 'Español (México)',
            self::UKRAINIAN => 'Українська',
            default => throw new InvalidArgumentException("Invalid language key: $key"),
        };
    }

    public static function flag(string $key): string
    {
        return match ($key) {
            self::RUSSIAN => '🇷🇺',
            self::GERMAN => '🇩🇪',
            self::FRENCH => '🇫🇷',
            self::ITALIAN => '🇮🇹',
            self::ENGLISH => '🇬🇧',
            self::SPANISH => '🇪🇸',
            self::POLISH => '🇵🇱',
            self::PORTUGUESE => '🇵🇹',
            self::UKRAINIAN => '🇺🇦',
            self::ROMANIAN => '🇷🇴',
            self::DUTCH => '🇳🇱',
            self::DUTCH_BELGIUM => '🇳🇱',
            self::FRENCH_BELGIUM => '🇧🇪',
            self::FRENCH_CANADA => '🇨🇦',
            self::FRENCH_SWITZERLAND => '🇨🇭',
            self::GERMAN_AUSTRIA => '🇦🇹',
            self::GERMAN_SWITZERLAND => '🇨🇭',
            self::PORTUGUESE_BRAZIL => '🇧🇷',
            self::SPANISH_ARGENTINA => '🇦🇷',
            self::SPANISH_COLOMBIA => '🇨🇴',
            self::SPANISH_MEXICO => '🇲🇽',
            default => throw new InvalidArgumentException("Invalid language key: $key"),
        };
    }

    /**
     * Determines whether the language is primary or secondary
     *
     * @param  string  $key
     * @return string 'main' ou 'secondary'
     */
    public static function getLanguageType($key): string
    {
        $mainLanguages = [
            self::ENGLISH,
            self::FRENCH,
            self::GERMAN,
            self::SPANISH,
            self::ITALIAN,
            self::PORTUGUESE,
        ];

        return in_array($key, $mainLanguages) ? 'main' : 'secondary';
    }

    /**
     * Returns the fallback language for a given language
     *
     * @param  string  $key
     */
    public static function getFallbackLanguage($key): string
    {
        return match ($key) {
            // Specific cases where a language other than English might be a better fallback
            self::FRENCH, self::FRENCH_BELGIUM, self::FRENCH_SWITZERLAND, self::FRENCH_CANADA => self::FRENCH,
            self::SPANISH, self::SPANISH_MEXICO, self::SPANISH_ARGENTINA, self::SPANISH_COLOMBIA => self::SPANISH,
            self::PORTUGUESE, self::PORTUGUESE_BRAZIL => self::PORTUGUESE,
            self::GERMAN, self::GERMAN_AUSTRIA, self::GERMAN_SWITZERLAND => self::GERMAN,
            self::ITALIAN => self::ITALIAN,
            self::POLISH => self::POLISH,
            self::UKRAINIAN => self::UKRAINIAN,
            self::RUSSIAN => self::RUSSIAN,
            self::DUTCH_BELGIUM => self::DUTCH,

            // English is the default fallback language
            default => self::FRENCH,
        };
    }

    /**
     * Get languages as select object filtered by LocalizationSettings
     *
     * @return array<int, array{label: string, value: string}>
     */
    public static function asSelectObjectFromSettings(): array
    {
        $localizationSettings = app(LocalizationSettings::class);
        $availableLocales = $localizationSettings->available_locales;

        /** @var array<int, array{label: string, value: string}> */
        $result = collect(self::asSelectObject())
            ->filter(fn (array $item): bool => in_array($item['value'], $availableLocales, true))
            ->values()
            ->toArray();

        return $result;
    }
}
