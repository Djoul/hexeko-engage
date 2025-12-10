<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Enums;

use App\Enums\Languages;
use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * Default tags to be created for each financer.
 *
 * @method static static GENERAL_ANNOUNCEMENTS()
 * @method static static COMPANY_NEWS()
 * @method static static INTERNAL_EVENTS()
 * @method static static HR_CAREER()
 * @method static static EMPLOYEE_BENEFITS()
 * @method static static WELLBEING()
 * @method static static REGULATIONS()
 * @method static static TRAINING()
 * @method static static CULTURE_VALUES()
 * @method static static PRACTICAL_OFFICE_LIFE()
 *
 * @extends Enum<string>
 */
final class TagsDefault extends Enum implements LocalizedEnum
{
    /**
     * General announcements and news.
     */
    public const GENERAL_ANNOUNCEMENTS = 'general_announcements';

    /**
     * Company-specific news and updates.
     */
    public const COMPANY_NEWS = 'company_news';

    /**
     * Internal events and gatherings.
     */
    public const INTERNAL_EVENTS = 'internal_events';

    /**
     * Human resources and career development.
     */
    public const HR_CAREER = 'hr_career';

    /**
     * Employee benefits and perks.
     */
    public const EMPLOYEE_BENEFITS = 'employee_benefits';

    /**
     * Wellbeing and health initiatives.
     */
    public const WELLBEING = 'wellbeing';

    /**
     * Company regulations and policies.
     */
    public const REGULATIONS = 'regulations';

    /**
     * Training and professional development.
     */
    public const TRAINING = 'training';

    /**
     * Company culture and core values.
     */
    public const CULTURE_VALUES = 'culture_values';

    /**
     * Practical day-to-day office life information.
     */
    public const PRACTICAL_OFFICE_LIFE = 'practical_office_life';

    /**
     * Get all default tag definitions with translations and legacy labels.
     *
     * @return array<int, array{key: string, translations: array<string, string>, legacy_english: array<int, string>}>
     */
    public static function getDefinitions(): array
    {
        return [
            [
                'key' => self::GENERAL_ANNOUNCEMENTS,
                'translations' => [
                    Languages::ENGLISH => 'General Announcements',
                    Languages::FRENCH_BELGIUM => 'Annonces générales',
                    Languages::DUTCH_BELGIUM => 'Algemene aankondigingen',
                    Languages::PORTUGUESE => 'Anúncios gerais',
                ],
                'legacy_english' => ['News'],
            ],
            [
                'key' => self::COMPANY_NEWS,
                'translations' => [
                    Languages::ENGLISH => 'Company News',
                    Languages::FRENCH_BELGIUM => 'Actualités de l\'entreprise',
                    Languages::DUTCH_BELGIUM => 'Bedrijfsnieuws',
                    Languages::PORTUGUESE => 'Notícias da empresa',
                ],
                'legacy_english' => ['Announcement'],
            ],
            [
                'key' => self::INTERNAL_EVENTS,
                'translations' => [
                    Languages::ENGLISH => 'Internal Events',
                    Languages::FRENCH_BELGIUM => 'Événements internes',
                    Languages::DUTCH_BELGIUM => 'Interne evenementen',
                    Languages::PORTUGUESE => 'Eventos internos',
                ],
                'legacy_english' => ['Event'],
            ],
            [
                'key' => self::HR_CAREER,
                'translations' => [
                    Languages::ENGLISH => 'HR & Career',
                    Languages::FRENCH_BELGIUM => 'RH & Carrière',
                    Languages::DUTCH_BELGIUM => 'HR & Loopbaan',
                    Languages::PORTUGUESE => 'RH e Carreira',
                ],
                'legacy_english' => ['HR'],
            ],
            [
                'key' => self::EMPLOYEE_BENEFITS,
                'translations' => [
                    Languages::ENGLISH => 'Employee Benefits',
                    Languages::FRENCH_BELGIUM => 'Avantages salariés',
                    Languages::DUTCH_BELGIUM => 'Werknemersvoordelen',
                    Languages::PORTUGUESE => 'Benefícios dos colaboradores',
                ],
                'legacy_english' => [],
            ],
            [
                'key' => self::WELLBEING,
                'translations' => [
                    Languages::ENGLISH => 'Wellbeing',
                    Languages::FRENCH_BELGIUM => 'Bien-être',
                    Languages::DUTCH_BELGIUM => 'Welzijn',
                    Languages::PORTUGUESE => 'Bem-estar',
                ],
                'legacy_english' => [],
            ],
            [
                'key' => self::REGULATIONS,
                'translations' => [
                    Languages::ENGLISH => 'Regulations',
                    Languages::FRENCH_BELGIUM => 'Règlement',
                    Languages::DUTCH_BELGIUM => 'Reglement',
                    Languages::PORTUGUESE => 'Regulamento',
                ],
                'legacy_english' => [],
            ],
            [
                'key' => self::TRAINING,
                'translations' => [
                    Languages::ENGLISH => 'Training',
                    Languages::FRENCH_BELGIUM => 'Formations',
                    Languages::DUTCH_BELGIUM => 'Opleidingen',
                    Languages::PORTUGUESE => 'Formações',
                ],
                'legacy_english' => ['Training'],
            ],
            [
                'key' => self::CULTURE_VALUES,
                'translations' => [
                    Languages::ENGLISH => 'Culture & Values',
                    Languages::FRENCH_BELGIUM => 'Culture & valeurs',
                    Languages::DUTCH_BELGIUM => 'Cultuur & Waarden',
                    Languages::PORTUGUESE => 'Cultura e Valores',
                ],
                'legacy_english' => [],
            ],
            [
                'key' => self::PRACTICAL_OFFICE_LIFE,
                'translations' => [
                    Languages::ENGLISH => 'Practical Office Life',
                    Languages::FRENCH_BELGIUM => 'Vie pratique au bureau',
                    Languages::DUTCH_BELGIUM => 'Praktisch leven op kantoor',
                    Languages::PORTUGUESE => 'Vida prática no escritório',
                ],
                'legacy_english' => [],
            ],
        ];
    }

    /**
     * Get translations for a specific tag key.
     *
     * @return array<string, string>
     */
    public static function getTranslations(string $key): array
    {
        $definitions = self::getDefinitions();

        foreach ($definitions as $definition) {
            if ($definition['key'] === $key) {
                return $definition['translations'];
            }
        }

        return [];
    }

    /**
     * Get legacy English labels for a specific tag key.
     *
     * @return array<int, string>
     */
    public static function getLegacyLabels(string $key): array
    {
        $definitions = self::getDefinitions();

        foreach ($definitions as $definition) {
            if ($definition['key'] === $key) {
                return $definition['legacy_english'];
            }
        }

        return [];
    }
}
