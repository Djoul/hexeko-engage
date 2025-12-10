<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum representing the different status states for Financer entities.
 *
 * @method static static ACTIVE()
 * @method static static PENDING()
 * @method static static ARCHIVED()
 */
class FinancerStatus extends BaseEnum
{
    public const ACTIVE = 'active';

    public const PENDING = 'pending';

    public const ARCHIVED = 'archived';

    /**
     * Check if a value is a valid status.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::getValues(), true);
    }
}
