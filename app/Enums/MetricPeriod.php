<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * @method static static SEVEN_DAYS()
 * @method static static THIRTY_DAYS()
 * @method static static THREE_MONTHS()
 * @method static static SIX_MONTHS()
 * @method static static TWELVE_MONTHS()
 *
 * @extends Enum<string>
 */
final class MetricPeriod extends Enum implements LocalizedEnum
{
    public const SEVEN_DAYS = '7d';

    public const THIRTY_DAYS = '30d';

    public const THREE_MONTHS = '3m';

    public const SIX_MONTHS = '6m';

    public const TWELVE_MONTHS = '12m';

    public const CUSTOM = 'custom';

    /**
     * Get all valid period values as array
     *
     * @return array<int, string>
     */
    public static function getValidPeriods(): array
    {
        return [
            self::SEVEN_DAYS,
            self::THIRTY_DAYS,
            self::THREE_MONTHS,
            self::SIX_MONTHS,
            self::TWELVE_MONTHS,
            self::CUSTOM,
        ];
    }

    /**
     * Check if period is a time-based period (not custom)
     */
    public static function isTimeBased(string $period): bool
    {
        return in_array($period, [
            self::SEVEN_DAYS,
            self::THIRTY_DAYS,
            self::THREE_MONTHS,
            self::SIX_MONTHS,
            self::TWELVE_MONTHS,
        ]);
    }

    /**
     * Get default period
     */
    public static function getDefault(): string
    {
        return self::THIRTY_DAYS;
    }

    /**
     * Get the date range for a given period based on a reference date
     *
     * @return array{from: Carbon, to: Carbon}
     */
    public static function getDateRange(string $period, ?Carbon $referenceDate = null): array
    {

        $reference = $referenceDate ?? Carbon::now();

        $dateTo = $referenceDate instanceof Carbon ? $reference->copy()->endOfDay() : Carbon::now()->startOfDay();

        return match ($period) {
            self::SEVEN_DAYS => [
                'from' => $reference->copy()->subDays(6)->startOfDay(),
                'to' => $dateTo,
            ],
            self::THIRTY_DAYS => [
                'from' => $reference->copy()->subDays(29)->startOfDay(),
                'to' => $dateTo,
            ],
            self::THREE_MONTHS => [
                'from' => $reference->copy()->subMonths(3)->addDay()->startOfDay(),
                'to' => $dateTo,
            ],
            self::SIX_MONTHS => [
                'from' => $reference->copy()->subMonths(6)->addDay()->startOfDay(),
                'to' => $dateTo,
            ],
            self::TWELVE_MONTHS => [
                'from' => $reference->copy()->subYear()->addDay()->startOfDay(),
                'to' => $dateTo,
            ],
            default => throw new InvalidArgumentException("Cannot calculate date range for period: {$period}"),
        };
    }
}
