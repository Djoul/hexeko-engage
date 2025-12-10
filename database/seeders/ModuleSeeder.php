<?php

namespace Database\Seeders;

use App\Enums\Languages;
use App\Enums\ModulesCategories;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Module;
use DB;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('modules')->truncate();
        DB::table('financer_module')->truncate();

        // Fixed UUIDs for consistent module identification
        $moduleUuids = [
            'internal_link' => '550e8400-e29b-41d4-a716-446655440001',
            'internal_communication' => '550e8400-e29b-41d4-a716-446655440002',
            'wellness' => '550e8400-e29b-41d4-a716-446655440003',
            'vouchers' => '550e8400-e29b-41d4-a716-446655440004',
            'survey' => '019a7c8a-9e05-737b-93ce-fa3299d62ba7',
        ];

        $modules = [
            [
                'id' => $moduleUuids['internal_link'],
                'name' => [
                    Languages::ENGLISH => 'Internal Link',
                    Languages::FRENCH => 'Lien Interne',
                    Languages::FRENCH_BELGIUM => 'Lien Interne',
                    Languages::FRENCH_CANADA => 'Lien Interne',
                    Languages::FRENCH_SWITZERLAND => 'Lien Interne',
                    Languages::GERMAN => 'Interner Link',
                    Languages::GERMAN_AUSTRIA => 'Interner Link',
                    Languages::GERMAN_SWITZERLAND => 'Interner Link',
                    Languages::SPANISH => 'Enlace Interno',
                    Languages::SPANISH_ARGENTINA => 'Enlace Interno',
                    Languages::SPANISH_COLOMBIA => 'Enlace Interno',
                    Languages::SPANISH_MEXICO => 'Enlace Interno',
                    Languages::ITALIAN => 'Link Interno',
                    Languages::PORTUGUESE => 'Link Interno',
                    Languages::PORTUGUESE_BRAZIL => 'Link Interno',
                    Languages::DUTCH => 'Interne Link',
                    Languages::DUTCH_BELGIUM => 'Interne Link',
                    Languages::POLISH => 'Link Wewnętrzny',
                    Languages::ROMANIAN => 'Link Intern',
                    Languages::RUSSIAN => 'Внутренняя ссылка',
                    Languages::UKRAINIAN => 'Внутрішнє посилання',
                ],
                'description' => [
                    Languages::ENGLISH => 'This module is about HR tools',
                    Languages::FRENCH => 'Ce module concerne les outils RH',
                    Languages::FRENCH_BELGIUM => 'Ce module concerne les outils RH',
                    Languages::FRENCH_CANADA => 'Ce module concerne les outils RH',
                    Languages::FRENCH_SWITZERLAND => 'Ce module concerne les outils RH',
                    Languages::GERMAN => 'Dieses Modul behandelt HR-Tools',
                    Languages::GERMAN_AUSTRIA => 'Dieses Modul behandelt HR-Tools',
                    Languages::GERMAN_SWITZERLAND => 'Dieses Modul behandelt HR-Tools',
                    Languages::SPANISH => 'Este módulo trata sobre herramientas de RRHH',
                    Languages::SPANISH_ARGENTINA => 'Este módulo trata sobre herramientas de RRHH',
                    Languages::SPANISH_COLOMBIA => 'Este módulo trata sobre herramientas de RRHH',
                    Languages::SPANISH_MEXICO => 'Este módulo trata sobre herramientas de RRHH',
                    Languages::ITALIAN => 'Questo modulo riguarda gli strumenti HR',
                    Languages::PORTUGUESE => 'Este módulo é sobre ferramentas de RH',
                    Languages::PORTUGUESE_BRAZIL => 'Este módulo é sobre ferramentas de RH',
                    Languages::DUTCH => 'Deze module gaat over HR-tools',
                    Languages::DUTCH_BELGIUM => 'Deze module gaat over HR-tools',
                    Languages::POLISH => 'Ten moduł dotyczy narzędzi HR',
                    Languages::ROMANIAN => 'Acest modul este despre instrumentele HR',
                    Languages::RUSSIAN => 'Этот модуль о HR инструментах',
                    Languages::UKRAINIAN => 'Цей модуль про HR інструменти',
                ],
                'category' => ModulesCategories::ENTERPRISE_LIFE,
            ],
            [
                'id' => $moduleUuids['internal_communication'],
                'name' => [
                    Languages::ENGLISH => 'Internal Communication',
                    Languages::FRENCH => 'Communication Interne',
                    Languages::FRENCH_BELGIUM => 'Communication Interne',
                    Languages::FRENCH_CANADA => 'Communication Interne',
                    Languages::FRENCH_SWITZERLAND => 'Communication Interne',
                    Languages::GERMAN => 'Interne Kommunikation',
                    Languages::GERMAN_AUSTRIA => 'Interne Kommunikation',
                    Languages::GERMAN_SWITZERLAND => 'Interne Kommunikation',
                    Languages::SPANISH => 'Comunicación Interna',
                    Languages::SPANISH_ARGENTINA => 'Comunicación Interna',
                    Languages::SPANISH_COLOMBIA => 'Comunicación Interna',
                    Languages::SPANISH_MEXICO => 'Comunicación Interna',
                    Languages::ITALIAN => 'Comunicazione Interna',
                    Languages::PORTUGUESE => 'Comunicação Interna',
                    Languages::PORTUGUESE_BRAZIL => 'Comunicação Interna',
                    Languages::DUTCH => 'Interne Communicatie',
                    Languages::DUTCH_BELGIUM => 'Interne Communicatie',
                    Languages::POLISH => 'Komunikacja Wewnętrzna',
                    Languages::ROMANIAN => 'Comunicare Internă',
                    Languages::RUSSIAN => 'Внутренняя коммуникация',
                    Languages::UKRAINIAN => 'Внутрішня комунікація',
                ],
                'description' => [
                    Languages::ENGLISH => 'This module is about HR space',
                    Languages::FRENCH => 'Ce module concerne l\'espace RH',
                    Languages::FRENCH_BELGIUM => 'Ce module concerne l\'espace RH',
                    Languages::FRENCH_CANADA => 'Ce module concerne l\'espace RH',
                    Languages::FRENCH_SWITZERLAND => 'Ce module concerne l\'espace RH',
                    Languages::GERMAN => 'Dieses Modul behandelt den HR-Bereich',
                    Languages::GERMAN_AUSTRIA => 'Dieses Modul behandelt den HR-Bereich',
                    Languages::GERMAN_SWITZERLAND => 'Dieses Modul behandelt den HR-Bereich',
                    Languages::SPANISH => 'Este módulo trata sobre el espacio de RRHH',
                    Languages::SPANISH_ARGENTINA => 'Este módulo trata sobre el espacio de RRHH',
                    Languages::SPANISH_COLOMBIA => 'Este módulo trata sobre el espacio de RRHH',
                    Languages::SPANISH_MEXICO => 'Este módulo trata sobre el espacio de RRHH',
                    Languages::ITALIAN => 'Questo modulo riguarda lo spazio HR',
                    Languages::PORTUGUESE => 'Este módulo é sobre o espaço de RH',
                    Languages::PORTUGUESE_BRAZIL => 'Este módulo é sobre o espaço de RH',
                    Languages::DUTCH => 'Deze module gaat over de HR-ruimte',
                    Languages::DUTCH_BELGIUM => 'Deze module gaat over de HR-ruimte',
                    Languages::POLISH => 'Ten moduł dotyczy przestrzeni HR',
                    Languages::ROMANIAN => 'Acest modul este despre spațiul HR',
                    Languages::RUSSIAN => 'Этот модуль о пространстве HR',
                    Languages::UKRAINIAN => 'Цей модуль про простір HR',
                ],
                'category' => ModulesCategories::ENTERPRISE_LIFE,
            ],
            [
                'id' => $moduleUuids['wellness'],
                'name' => [
                    Languages::ENGLISH => 'Wellness',
                    Languages::FRENCH => 'Bien-être',
                    Languages::FRENCH_BELGIUM => 'Bien-être',
                    Languages::FRENCH_CANADA => 'Bien-être',
                    Languages::FRENCH_SWITZERLAND => 'Bien-être',
                    Languages::GERMAN => 'Wohlbefinden',
                    Languages::GERMAN_AUSTRIA => 'Wohlbefinden',
                    Languages::GERMAN_SWITZERLAND => 'Wohlbefinden',
                    Languages::SPANISH => 'Bienestar',
                    Languages::SPANISH_ARGENTINA => 'Bienestar',
                    Languages::SPANISH_COLOMBIA => 'Bienestar',
                    Languages::SPANISH_MEXICO => 'Bienestar',
                    Languages::ITALIAN => 'Benessere',
                    Languages::PORTUGUESE => 'Bem-estar',
                    Languages::PORTUGUESE_BRAZIL => 'Bem-estar',
                    Languages::DUTCH => 'Welzijn',
                    Languages::DUTCH_BELGIUM => 'Welzijn',
                    Languages::POLISH => 'Dobrostan',
                    Languages::ROMANIAN => 'Bunăstare',
                    Languages::RUSSIAN => 'Благополучие',
                    Languages::UKRAINIAN => 'Благополуччя',
                ],
                'description' => [
                    Languages::ENGLISH => 'This module is about wellness',
                    Languages::FRENCH => 'Ce module concerne le bien-être',
                    Languages::FRENCH_BELGIUM => 'Ce module concerne le bien-être',
                    Languages::FRENCH_CANADA => 'Ce module concerne le bien-être',
                    Languages::FRENCH_SWITZERLAND => 'Ce module concerne le bien-être',
                    Languages::GERMAN => 'Dieses Modul behandelt das Wohlbefinden',
                    Languages::GERMAN_AUSTRIA => 'Dieses Modul behandelt das Wohlbefinden',
                    Languages::GERMAN_SWITZERLAND => 'Dieses Modul behandelt das Wohlbefinden',
                    Languages::SPANISH => 'Este módulo trata sobre el bienestar',
                    Languages::SPANISH_ARGENTINA => 'Este módulo trata sobre el bienestar',
                    Languages::SPANISH_COLOMBIA => 'Este módulo trata sobre el bienestar',
                    Languages::SPANISH_MEXICO => 'Este módulo trata sobre el bienestar',
                    Languages::ITALIAN => 'Questo modulo riguarda il benessere',
                    Languages::PORTUGUESE => 'Este módulo é sobre bem-estar',
                    Languages::PORTUGUESE_BRAZIL => 'Este módulo é sobre bem-estar',
                    Languages::DUTCH => 'Deze module gaat over welzijn',
                    Languages::DUTCH_BELGIUM => 'Deze module gaat over welzijn',
                    Languages::POLISH => 'Ten moduł dotyczy dobrostanu',
                    Languages::ROMANIAN => 'Acest modul este despre bunăstare',
                    Languages::RUSSIAN => 'Этот модуль о благополучии',
                    Languages::UKRAINIAN => 'Цей модуль про благополуччя',
                ],
                'category' => ModulesCategories::WELLBEING,
            ],
            [
                'id' => $moduleUuids['vouchers'],
                'name' => [
                    Languages::ENGLISH => 'Vouchers',
                    Languages::FRENCH => 'Bons d\'achat',
                    Languages::FRENCH_BELGIUM => 'Bons d\'achat',
                    Languages::FRENCH_CANADA => 'Bons d\'achat',
                    Languages::FRENCH_SWITZERLAND => 'Bons d\'achat',
                    Languages::GERMAN => 'Gutscheine',
                    Languages::GERMAN_AUSTRIA => 'Gutscheine',
                    Languages::GERMAN_SWITZERLAND => 'Gutscheine',
                    Languages::SPANISH => 'Vales',
                    Languages::SPANISH_ARGENTINA => 'Vales',
                    Languages::SPANISH_COLOMBIA => 'Vales',
                    Languages::SPANISH_MEXICO => 'Vales',
                    Languages::ITALIAN => 'Buoni',
                    Languages::PORTUGUESE => 'Vales',
                    Languages::PORTUGUESE_BRAZIL => 'Vales',
                    Languages::DUTCH => 'Bonnen',
                    Languages::DUTCH_BELGIUM => 'Bonnen',
                    Languages::POLISH => 'Vouchery',
                    Languages::ROMANIAN => 'Vouchere',
                    Languages::RUSSIAN => 'Ваучеры',
                    Languages::UKRAINIAN => 'Ваучери',
                ],
                'description' => [
                    Languages::ENGLISH => 'This module is about vouchers',
                    Languages::FRENCH => 'Ce module concerne les bons d\'achat',
                    Languages::FRENCH_BELGIUM => 'Ce module concerne les bons d\'achat',
                    Languages::FRENCH_CANADA => 'Ce module concerne les bons d\'achat',
                    Languages::FRENCH_SWITZERLAND => 'Ce module concerne les bons d\'achat',
                    Languages::GERMAN => 'Dieses Modul behandelt Gutscheine',
                    Languages::GERMAN_AUSTRIA => 'Dieses Modul behandelt Gutscheine',
                    Languages::GERMAN_SWITZERLAND => 'Dieses Modul behandelt Gutscheine',
                    Languages::SPANISH => 'Este módulo trata sobre vales',
                    Languages::SPANISH_ARGENTINA => 'Este módulo trata sobre vales',
                    Languages::SPANISH_COLOMBIA => 'Este módulo trata sobre vales',
                    Languages::SPANISH_MEXICO => 'Este módulo trata sobre vales',
                    Languages::ITALIAN => 'Questo modulo riguarda i buoni',
                    Languages::PORTUGUESE => 'Este módulo é sobre vales',
                    Languages::PORTUGUESE_BRAZIL => 'Este módulo é sobre vales',
                    Languages::DUTCH => 'Deze module gaat over bonnen',
                    Languages::DUTCH_BELGIUM => 'Deze module gaat over bonnen',
                    Languages::POLISH => 'Ten moduł dotyczy voucherów',
                    Languages::ROMANIAN => 'Acest modul este despre vouchere',
                    Languages::RUSSIAN => 'Этот модуль о ваучерах',
                    Languages::UKRAINIAN => 'Цей модуль про ваучери',
                ],
                'category' => ModulesCategories::PURCHASING_POWER,
            ],
            [
                'id' => $moduleUuids['survey'],
                'name' => [
                    Languages::ENGLISH => 'Survey',
                    Languages::FRENCH => 'Sondage',
                    Languages::FRENCH_BELGIUM => 'Sondage',
                    Languages::FRENCH_CANADA => 'Sondage',
                    Languages::FRENCH_SWITZERLAND => 'Sondage',
                    Languages::GERMAN => 'Umfrage',
                    Languages::GERMAN_AUSTRIA => 'Umfrage',
                    Languages::GERMAN_SWITZERLAND => 'Umfrage',
                    Languages::SPANISH => 'Encuesta',
                    Languages::SPANISH_ARGENTINA => 'Encuesta',
                    Languages::SPANISH_COLOMBIA => 'Encuesta',
                    Languages::SPANISH_MEXICO => 'Encuesta',
                    Languages::ITALIAN => 'Sondaggio',
                    Languages::PORTUGUESE => 'Sondage',
                    Languages::PORTUGUESE_BRAZIL => 'Sondage',
                    Languages::DUTCH => 'Sondage',
                    Languages::DUTCH_BELGIUM => 'Sondage',
                    Languages::POLISH => 'Badanie',
                    Languages::ROMANIAN => 'Sondaj',
                    Languages::RUSSIAN => 'Опрос',
                    Languages::UKRAINIAN => 'Опитування',
                ],
                'description' => [
                    Languages::ENGLISH => 'This module is about survey',
                    Languages::FRENCH => 'Ce module concerne le sondage',
                    Languages::FRENCH_BELGIUM => 'Ce module concerne le sondage',
                    Languages::FRENCH_CANADA => 'Ce module concerne le sondage',
                    Languages::FRENCH_SWITZERLAND => 'Ce module concerne le sondage',
                    Languages::GERMAN => 'Dieses Modul behandelt Umfragen',
                    Languages::GERMAN_AUSTRIA => 'Dieses Modul behandelt Umfragen',
                    Languages::GERMAN_SWITZERLAND => 'Dieses Modul behandelt Umfragen',
                    Languages::SPANISH => 'Este módulo trata sobre encuestas',
                    Languages::SPANISH_ARGENTINA => 'Este módulo trata sobre encuestas',
                    Languages::SPANISH_COLOMBIA => 'Este módulo trata sobre encuestas',
                    Languages::SPANISH_MEXICO => 'Este módulo trata sobre encuestas',
                    Languages::ITALIAN => 'Questo modulo riguarda i sondaggi',
                    Languages::PORTUGUESE => 'Este módulo é sobre enquêtes',
                    Languages::PORTUGUESE_BRAZIL => 'Este módulo é sobre enquêtes',
                    Languages::DUTCH => 'Deze module gaat over enquêtes',
                    Languages::DUTCH_BELGIUM => 'Deze module gaat over enquêtes',
                    Languages::POLISH => 'Ten moduł dotyczy badania',
                    Languages::ROMANIAN => 'Acest modul este despre sondaj',
                    Languages::RUSSIAN => 'Этот модуль о просмотрех',
                    Languages::UKRAINIAN => 'Цей модуль про опитування',
                ],
                'category' => ModulesCategories::ENTERPRISE_LIFE,
            ],
        ];

        foreach ($modules as $moduleData) {
            Module::create([
                'id' => $moduleData['id'],
                'name' => $moduleData['name'],
                'description' => $moduleData['description'],
                'category' => $moduleData['category'],
            ]);
        }

        // Mark core modules (first 3 modules as per specification)
        $this->markCoreModules();

        Division::get()->each(function (Division $division): void {
            Module::get()
                ->each(function (Module $module) use ($division): void {
                    if (! $division->modules()->wherePivot(
                        'module_id', $module->id)->exists()) {
                        $division->modules()
                            ->attach(
                                $module->id,
                                [
                                    'active' => true,
                                    'price_per_beneficiary' => $module->is_core ? null : 100,
                                ]

                            );
                    }
                });
        });

        Financer::get()->each(function (Financer $financer): void {
            Module::get()
                ->each(function (Module $module) use ($financer): void {
                    if (! $financer->modules()->wherePivot('module_id', $module->id)->exists()) {
                        $financer->modules()
                            ->attach(
                                $module->id,
                                [
                                    'active' => true,
                                    'price_per_beneficiary' => $module->is_core ? null : 200,
                                ]
                            );
                    }
                });
        });
    }

    /**
     * Mark specific modules as core modules that cannot be deactivated
     */
    private function markCoreModules(): void
    {
        $coreModuleIds = [
            '550e8400-e29b-41d4-a716-446655440001', // Internal Link
            '550e8400-e29b-41d4-a716-446655440002', // Internal Communication
            '019a7c8a-9e05-737b-93ce-fa3299d62ba7', // Survey
        ];

        DB::table('modules')
            ->whereIn('id', $coreModuleIds)
            ->update(['is_core' => true]);
    }
}
