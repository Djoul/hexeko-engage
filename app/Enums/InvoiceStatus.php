<?php

namespace App\Enums;

/**
 * @method static static DRAFT()
 * @method static static CONFIRMED()
 * @method static static SENT()
 * @method static static PAID()
 * @method static static OVERDUE()
 * @method static static CANCELLED()
 */
class InvoiceStatus extends BaseEnum
{
    public const DRAFT = 'draft';

    public const CONFIRMED = 'confirmed';

    public const SENT = 'sent';

    public const PAID = 'paid';

    public const OVERDUE = 'overdue';

    public const CANCELLED = 'cancelled';

    public static function getDescription(mixed $value): string
    {
        return match ($value) {
            self::DRAFT => 'Brouillon',
            self::CONFIRMED => 'Confirmée',
            self::SENT => 'Envoyée',
            self::PAID => 'Payée',
            self::OVERDUE => 'En retard',
            self::CANCELLED => 'Annulée',
            default => parent::getDescription($value),
        };
    }
}
