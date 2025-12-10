<?php

namespace App\Enums;

use InvalidArgumentException;

final class Currencies extends BaseEnum
{
    const USD = 'USD'; // US Dollar

    const EUR = 'EUR'; // Euro

    const GBP = 'GBP'; // British Pound

    const JPY = 'JPY'; // Japanese Yen

    const CHF = 'CHF'; // Swiss Franc

    /**
     * Get the symbol of the currency.
     *
     * @param  string  $key
     */
    public function symbol($key): string
    {
        return match ($key) {
            self::USD => '$',
            self::EUR => '€',
            self::GBP => '£',
            self::JPY => '¥',
            self::CHF => 'CHF',
            default => throw new InvalidArgumentException("Invalid currency key: $key"),
        };
    }
}
