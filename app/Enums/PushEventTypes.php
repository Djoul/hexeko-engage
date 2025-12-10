<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * @method static static SENT()
 * @method static static DELIVERED()
 * @method static static OPENED()
 * @method static static CLICKED()
 * @method static static DISMISSED()
 * @method static static FAILED()
 */
final class PushEventTypes extends Enum implements LocalizedEnum
{
    const SENT = 'sent';

    const DELIVERED = 'delivered';

    const OPENED = 'opened';

    const CLICKED = 'clicked';

    const DISMISSED = 'dismissed';

    const FAILED = 'failed';

    /**
     * Get the description for an enum value
     */
    public static function getDescription(mixed $value): string
    {
        return match ($value) {
            self::SENT => 'Sent',
            self::DELIVERED => 'Delivered',
            self::OPENED => 'Opened',
            self::CLICKED => 'Clicked',
            self::DISMISSED => 'Dismissed',
            self::FAILED => 'Failed',
            default => parent::getDescription($value),
        };
    }

    /**
     * Get the label for this enum instance
     */
    public function label(): string
    {
        return self::getDescription($this->value);
    }

    /**
     * Check if event is successful
     */
    public function isSuccessful(): bool
    {
        return $this->in([
            self::SENT,
            self::DELIVERED,
            self::OPENED,
            self::CLICKED,
        ]);
    }

    /**
     * Check if event represents user engagement
     */
    public function isEngagement(): bool
    {
        return $this->in([
            self::OPENED,
            self::CLICKED,
        ]);
    }

    /**
     * Get successful event types
     */
    public static function getSuccessfulTypes(): array
    {
        return [
            self::SENT,
            self::DELIVERED,
            self::OPENED,
            self::CLICKED,
        ];
    }

    /**
     * Get engagement event types
     */
    public static function getEngagementTypes(): array
    {
        return [
            self::OPENED,
            self::CLICKED,
        ];
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_values(self::asArray());
    }
}
