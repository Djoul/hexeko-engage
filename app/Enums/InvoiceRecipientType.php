<?php

namespace App\Enums;

/**
 * @method static static DIVISION()
 * @method static static FINANCER()
 */
class InvoiceRecipientType extends BaseEnum
{
    public const DIVISION = 'division';

    public const FINANCER = 'financer';

    public static function getDescription(mixed $value): string
    {
        return match ($value) {
            self::DIVISION => 'Division',
            self::FINANCER => 'Financeur',
            default => parent::getDescription($value),
        };
    }
}
