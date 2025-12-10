<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * @method static static TRANSACTION()
 * @method static static MARKETING()
 * @method static static SYSTEM()
 * @method static static REMINDER()
 * @method static static ALERT()
 */
final class NotificationTypes extends Enum implements LocalizedEnum
{
    const TRANSACTION = 'transaction';

    const MARKETING = 'marketing';

    const SYSTEM = 'system';

    const REMINDER = 'reminder';

    const ALERT = 'alert';

    /**
     * Get the description for an enum value
     */
    public static function getDescription(mixed $value): string
    {
        return match ($value) {
            self::TRANSACTION => 'Transaction Update',
            self::MARKETING => 'Marketing Campaign',
            self::SYSTEM => 'System Notification',
            self::REMINDER => 'Reminder',
            self::ALERT => 'Alert',
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
     * Get priority for notification type
     */
    public function priority(): string
    {
        return match ($this->value) {
            self::TRANSACTION => 'high',
            self::MARKETING => 'low',
            self::SYSTEM, self::ALERT => 'urgent',
            self::REMINDER => 'normal',
            default => 'normal',
        };
    }

    /**
     * Check if notification type is critical
     */
    public function isCritical(): bool
    {
        return $this->in([self::SYSTEM, self::ALERT]);
    }

    /**
     * Check if notification type requires opt-in
     */
    public function requiresOptIn(): bool
    {
        return $this->in([self::MARKETING, self::REMINDER]);
    }

    /**
     * Get critical notification types
     */
    public static function getCriticalTypes(): array
    {
        return [
            self::SYSTEM,
            self::ALERT,
        ];
    }

    /**
     * Get opt-in required types
     */
    public static function getOptInTypes(): array
    {
        return [
            self::MARKETING,
            self::REMINDER,
        ];
    }

    /**
     * Get priority map
     */
    public static function getPriorityMap(): array
    {
        return [
            self::TRANSACTION => 'high',
            self::MARKETING => 'low',
            self::SYSTEM => 'urgent',
            self::REMINDER => 'normal',
            self::ALERT => 'urgent',
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
