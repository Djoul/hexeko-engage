<?php

namespace App\Enums;

/**
 * @method static static HEXEKO()
 * @method static static DIVISION()
 */
class InvoiceIssuerType extends BaseEnum
{
    public const HEXEKO = 'hexeko';

    public const DIVISION = 'division';

    public static function getDescription(mixed $value): string
    {
        return match ($value) {
            self::HEXEKO => 'Hexeko',
            self::DIVISION => 'Division',
            default => parent::getDescription($value),
        };
    }
}
