<?php

namespace App\Enums;

/**
 * @method static static HEXEKO_TO_DIVISION()
 * @method static static DIVISION_TO_FINANCER()
 */
class InvoiceType extends BaseEnum
{
    public const HEXEKO_TO_DIVISION = 'hexeko_to_division';

    public const DIVISION_TO_FINANCER = 'division_to_financer';

    public static function getDescription(mixed $value): string
    {
        return match ($value) {
            self::HEXEKO_TO_DIVISION => 'Hexeko vers division',
            self::DIVISION_TO_FINANCER => 'Division vers financeur',
            default => parent::getDescription($value),
        };
    }
}
