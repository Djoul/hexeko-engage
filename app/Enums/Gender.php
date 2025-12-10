<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * @method static static MALE()
 * @method static static FEMALE()
 * @method static static UNISEX()
 * @method static static OTHER()
 * @method static static NOT_SPECIFIED()
 */
final class Gender extends BaseEnum
{
    const MALE = 'male';

    const FEMALE = 'female';

    const UNISEX = 'unisex';

    const OTHER = 'other';

    const NOT_SPECIFIED = 'not_specified';

    /**
     * Override to disable localization for this enum.
     */
    protected static function isLocalizable(): bool
    {
        return false;
    }
}
