<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * @method static static IOS()
 * @method static static ANDROID()
 * @method static static WEB()
 * @method static static DESKTOP()
 */
final class DeviceTypes extends Enum implements LocalizedEnum
{
    const IOS = 'ios';

    const ANDROID = 'android';

    const WEB = 'web';

    const DESKTOP = 'desktop';

    /**
     * Get the description for an enum value
     */
    public static function getDescription(mixed $value): string
    {
        return match ($value) {
            self::IOS => 'iOS',
            self::ANDROID => 'Android',
            self::WEB => 'Web Browser',
            self::DESKTOP => 'Desktop App',
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
     * Check if device type is mobile
     */
    public function isMobile(): bool
    {
        return $this->in([self::IOS, self::ANDROID]);
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_values(self::asArray());
    }
}
