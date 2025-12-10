<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\FinancerMetricType;
use App\Enums\IDP\RoleDefaults;
use App\Enums\MetricPeriod;
use App\Integrations\InternalCommunication\Enums\StatusArticleEnum;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('translation')]
class EnumTranslationTest extends TestCase
{
    #[Test]
    public function it_translates_financer_metric_type_to_french(): void
    {
        App::setLocale('fr-FR');

        $this->assertEquals(
            'Bénéficiaires actifs',
            FinancerMetricType::getDescription(FinancerMetricType::ACTIVE_BENEFICIARIES)
        );

        $this->assertEquals(
            "Taux d'activation",
            FinancerMetricType::getDescription(FinancerMetricType::ACTIVATION_RATE)
        );
    }

    #[Test]
    public function it_translates_financer_metric_type_to_portuguese(): void
    {
        App::setLocale('pt-PT');

        $this->assertEquals(
            'Beneficiários ativos',
            FinancerMetricType::getDescription(FinancerMetricType::ACTIVE_BENEFICIARIES)
        );

        $this->assertEquals(
            'Taxa de ativação',
            FinancerMetricType::getDescription(FinancerMetricType::ACTIVATION_RATE)
        );
    }

    #[Test]
    public function it_translates_financer_metric_type_to_dutch(): void
    {
        App::setLocale('nl-NL');

        $this->assertEquals(
            'Actieve begunstigden',
            FinancerMetricType::getDescription(FinancerMetricType::ACTIVE_BENEFICIARIES)
        );

        $this->assertEquals(
            'Activeringspercentage',
            FinancerMetricType::getDescription(FinancerMetricType::ACTIVATION_RATE)
        );
    }

    #[Test]
    public function it_translates_financer_metric_type_to_english(): void
    {
        App::setLocale('en-GB');

        $this->assertEquals(
            'Active beneficiaries',
            FinancerMetricType::getDescription(FinancerMetricType::ACTIVE_BENEFICIARIES)
        );

        $this->assertEquals(
            'Activation rate',
            FinancerMetricType::getDescription(FinancerMetricType::ACTIVATION_RATE)
        );
    }

    #[Test]
    public function it_translates_role_defaults_to_all_locales(): void
    {
        $translations = [
            'fr-FR' => [
                RoleDefaults::GOD => 'Dieu',
                RoleDefaults::BENEFICIARY => 'Bénéficiaire',
                RoleDefaults::FINANCER_ADMIN => 'Administrateur Financeur',
            ],
            'pt-PT' => [
                RoleDefaults::GOD => 'Deus',
                RoleDefaults::BENEFICIARY => 'Beneficiário',
                RoleDefaults::FINANCER_ADMIN => 'Administrador Financiador',
            ],
            'nl-NL' => [
                RoleDefaults::GOD => 'God',
                RoleDefaults::BENEFICIARY => 'Begunstigde',
                RoleDefaults::FINANCER_ADMIN => 'Beheerder Financier',
            ],
            'en-GB' => [
                RoleDefaults::GOD => 'God',
                RoleDefaults::BENEFICIARY => 'Beneficiary',
                RoleDefaults::FINANCER_ADMIN => 'Financer Administrator',
            ],
        ];

        foreach ($translations as $locale => $expectedTranslations) {
            App::setLocale($locale);

            foreach ($expectedTranslations as $value => $expectedText) {
                $this->assertEquals(
                    $expectedText,
                    RoleDefaults::getDescription($value),
                    "Failed for locale {$locale} and value {$value}"
                );
            }
        }
    }

    #[Test]
    public function it_translates_metric_period_to_all_locales(): void
    {
        $translations = [
            'fr-FR' => [
                MetricPeriod::SEVEN_DAYS => '7 derniers jours',
                MetricPeriod::THIRTY_DAYS => '30 derniers jours',
                MetricPeriod::CUSTOM => 'Période personnalisée',
            ],
            'pt-PT' => [
                MetricPeriod::SEVEN_DAYS => 'Últimos 7 dias',
                MetricPeriod::THIRTY_DAYS => 'Últimos 30 dias',
                MetricPeriod::CUSTOM => 'Período personalizado',
            ],
            'nl-NL' => [
                MetricPeriod::SEVEN_DAYS => 'Laatste 7 dagen',
                MetricPeriod::THIRTY_DAYS => 'Laatste 30 dagen',
                MetricPeriod::CUSTOM => 'Aangepaste periode',
            ],
            'en-GB' => [
                MetricPeriod::SEVEN_DAYS => 'Last 7 days',
                MetricPeriod::THIRTY_DAYS => 'Last 30 days',
                MetricPeriod::CUSTOM => 'Custom period',
            ],
        ];

        foreach ($translations as $locale => $expectedTranslations) {
            App::setLocale($locale);

            foreach ($expectedTranslations as $value => $expectedText) {
                $this->assertEquals(
                    $expectedText,
                    MetricPeriod::getDescription($value),
                    "Failed for locale {$locale} and value {$value}"
                );
            }
        }
    }

    #[Test]
    public function it_translates_status_article_enum_to_all_locales(): void
    {
        $translations = [
            'fr-FR' => [
                StatusArticleEnum::DRAFT => 'Brouillon',
                StatusArticleEnum::PUBLISHED => 'Publié',
                StatusArticleEnum::PENDING => 'En attente',
                StatusArticleEnum::DELETED => 'Supprimé',
            ],
            'pt-PT' => [
                StatusArticleEnum::DRAFT => 'Rascunho',
                StatusArticleEnum::PUBLISHED => 'Publicado',
                StatusArticleEnum::PENDING => 'Pendente',
                StatusArticleEnum::DELETED => 'Eliminado',
            ],
            'nl-NL' => [
                StatusArticleEnum::DRAFT => 'Concept',
                StatusArticleEnum::PUBLISHED => 'Gepubliceerd',
                StatusArticleEnum::PENDING => 'In behandeling',
                StatusArticleEnum::DELETED => 'Verwijderd',
            ],
            'en-GB' => [
                StatusArticleEnum::DRAFT => 'Draft',
                StatusArticleEnum::PUBLISHED => 'Published',
                StatusArticleEnum::PENDING => 'Pending',
                StatusArticleEnum::DELETED => 'Deleted',
            ],
        ];

        foreach ($translations as $locale => $expectedTranslations) {
            App::setLocale($locale);

            foreach ($expectedTranslations as $value => $expectedText) {
                $this->assertEquals(
                    $expectedText,
                    StatusArticleEnum::getDescription($value),
                    "Failed for locale {$locale} and value {$value}"
                );
            }
        }
    }

    #[Test]
    #[DataProvider('allLocalesProvider')]
    public function it_returns_description_for_all_enums_in_locale(string $locale): void
    {
        App::setLocale($locale);

        // Test a sample of enums to ensure they all have translations
        $enums = [
            [FinancerMetricType::class, FinancerMetricType::ACTIVE_BENEFICIARIES],
            [RoleDefaults::class, RoleDefaults::BENEFICIARY],
            [MetricPeriod::class, MetricPeriod::SEVEN_DAYS],
            [StatusArticleEnum::class, StatusArticleEnum::DRAFT],
        ];

        foreach ($enums as [$enumClass, $value]) {
            $description = $enumClass::getDescription($value);

            $this->assertNotEmpty($description, "Description is empty for {$enumClass}::{$value} in locale {$locale}");
            $this->assertIsString($description);
        }
    }

    public static function allLocalesProvider(): array
    {
        return [
            'French' => ['fr-FR'],
            'Portuguese' => ['pt-PT'],
            'Dutch' => ['nl-NL'],
            'English' => ['en-GB'],
        ];
    }
}
