<?php

namespace App\Enums;

/**
 * @method static static CORE_PACKAGE()
 * @method static static MODULE()
 * @method static static ADJUSTMENT()
 * @method static static OTHER()
 */
class InvoiceItemType extends BaseEnum
{
    public const CORE_PACKAGE = 'core_package';

    public const MODULE = 'module';

    public const ADJUSTMENT = 'adjustment';

    public const OTHER = 'other';

    public static function getDescription(mixed $value): string
    {
        return match ($value) {
            self::CORE_PACKAGE => 'Offre cœur',
            self::MODULE => 'Module',
            self::ADJUSTMENT => 'Ajustement',
            self::OTHER => 'Autre',
            default => parent::getDescription($value),
        };
    }
}
