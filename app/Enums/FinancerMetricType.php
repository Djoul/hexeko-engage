<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;
use UnexpectedValueException;

/**
 * Financer Metric Types
 *
 * Defines all available metric types for financer analytics.
 * Based on the original analysis with 11 detailed metrics.
 *
 * @extends Enum<string>
 */
final class FinancerMetricType extends Enum implements LocalizedEnum
{
    /**
     * Active beneficiaries count over time
     */
    const ACTIVE_BENEFICIARIES = 'active-beneficiaries';

    /**
     * User activation rate percentage
     */
    const ACTIVATION_RATE = 'activation-rate';

    /**
     * Median session time in minutes
     */
    const SESSION_TIME = 'session-time';

    /**
     * HR communications views and interactions
     */
    const ARTICLE_VIEWED = 'article_viewed';

    /**
     * Module usage statistics (multi-line)
     */
    const MODULE_USAGE = 'module-usage';

    /**
     * Voucher purchases volume in euros
     */
    const VOUCHER_PURCHASES = 'voucher-purchases';

    /**
     * Shortcuts clicks by type (multi-line)
     */
    const SHORTCUTS_CLICKS = 'shortcuts-clicks';

    /**
     * Article reactions (likes, shares, etc.)
     */
    const ARTICLE_REACTIONS = 'article-reactions';

    /**
     * Articles per active employee ratio
     */
    const ARTICLES_PER_EMPLOYEE = 'articles-per-employee';

    /**
     * Bounce rate percentage (sessions without interaction)
     */
    const BOUNCE_RATE = 'bounce-rate';

    /**
     * Average voucher amount per purchase
     */
    const VOUCHER_AVERAGE_AMOUNT = 'voucher-average-amount';

    /**
     * Get the corresponding database metric name for storage
     */
    public function getMetricName(): string
    {
        return match ($this->value) {
            self::ACTIVE_BENEFICIARIES => 'financer_active_beneficiaries',
            self::ACTIVATION_RATE => 'financer_activation_rate',
            self::SESSION_TIME => 'financer_session_time',
            self::ARTICLE_VIEWED => 'financer_article_viewed',
            self::MODULE_USAGE => 'financer_module_usage',
            self::VOUCHER_PURCHASES => 'financer_voucher_purchases',
            self::SHORTCUTS_CLICKS => 'financer_shortcuts_clicks',
            self::ARTICLE_REACTIONS => 'financer_article_reactions',
            self::ARTICLES_PER_EMPLOYEE => 'financer_articles_per_employee',
            self::BOUNCE_RATE => 'financer_bounce_rate',
            self::VOUCHER_AVERAGE_AMOUNT => 'financer_voucher_average',
            default => throw new UnexpectedValueException("Unknown metric type: {$this->value}"),
        };
    }

    /**
     * Get human-readable description for the metric
     */
    public function getMetricDescription(): string
    {
        return match ($this->value) {
            self::ACTIVE_BENEFICIARIES => 'Nombre unique d\'utilisateurs connectés par période',
            self::ACTIVATION_RATE => 'Pourcentage d\'utilisateurs ayant activé leur compte',
            self::SESSION_TIME => 'Durée médiane des sessions en minutes',
            self::ARTICLE_VIEWED => 'Nombre total de vues d\'articles RH',
            self::MODULE_USAGE => 'Statistiques d\'utilisation par module',
            self::VOUCHER_PURCHASES => 'Volume d\'achats de vouchers en euros',
            self::SHORTCUTS_CLICKS => 'Nombre de clics par type de raccourci',
            self::ARTICLE_REACTIONS => 'Total des réactions aux articles (likes)',
            self::ARTICLES_PER_EMPLOYEE => 'Moyenne d\'articles consultés par employé actif',
            self::BOUNCE_RATE => 'Pourcentage de sessions sans interaction',
            self::VOUCHER_AVERAGE_AMOUNT => 'Montant moyen par achat de voucher',
            default => throw new UnexpectedValueException("Unknown metric type: {$this->value}"),
        };
    }

    /**
     * Check if this metric type supports multi-line datasets
     */
    public function isMultiLine(): bool
    {
        return in_array($this->value, [
            self::MODULE_USAGE,
            self::SHORTCUTS_CLICKS,
        ]);
    }

    /**
     * Get appropriate interval for different time periods
     */
    public static function getIntervalForPeriod(string $period): string
    {
        return match ($period) {
            '7_days', '30_days' => 'daily',
            '3_months', '6_months' => 'weekly',
            '12_months' => 'monthly',
            default => 'daily',
        };
    }

    /**
     * Get all metric types as array for validation
     */
    public static function getRoutePattern(bool $onlyActive = true): string
    {
        $values = $onlyActive ? self::activeValues() : self::getValues();

        return implode('|', $values);
    }

    /**
     * Get the internal metric name (without financer_ prefix)
     */
    public function getInternalMetricName(): string
    {
        return match ($this->value) {
            self::ACTIVE_BENEFICIARIES => 'active_beneficiaries',
            self::ACTIVATION_RATE => 'activation_rate',
            self::SESSION_TIME => 'average_session_time',
            self::ARTICLE_VIEWED => 'article_viewed_views',
            self::MODULE_USAGE => 'module_usage',
            self::VOUCHER_PURCHASES => 'voucher_purchases',
            self::SHORTCUTS_CLICKS => 'shortcuts_clicks',
            self::ARTICLE_REACTIONS => 'article_reactions',
            self::ARTICLES_PER_EMPLOYEE => 'articles_per_employee',
            self::BOUNCE_RATE => 'communications_bounce_rate',
            self::VOUCHER_AVERAGE_AMOUNT => 'average_voucher_amount',
            default => throw new UnexpectedValueException("Unknown metric type: {$this->value}"),
        };
    }

    /**
     * Get only active metric values based on configuration.
     *
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        $disabled = array_flip(self::disabledValues());

        return array_values(array_filter(
            self::getValues(),
            static fn (string $metric): bool => ! array_key_exists($metric, $disabled)
        ));
    }

    /**
     * Determine if a metric type is currently active.
     */
    public static function isActive(string $metricType): bool
    {
        return in_array($metricType, self::activeValues(), true);
    }

    /**
     * Resolve disabled metric values from configuration.
     *
     * @return array<int, string>
     */
    private static function disabledValues(): array
    {
        $configured = config('metrics.disabled_metrics', []);
        if (! is_array($configured) || $configured === []) {
            return [];
        }

        $normalized = array_values(array_filter(array_map(
            static fn ($metric): ?string => is_string($metric) ? trim($metric) : null,
            $configured
        )));

        if ($normalized === []) {
            return [];
        }

        return array_values(array_intersect(self::getValues(), $normalized));
    }
}
