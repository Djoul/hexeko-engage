<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * @extends BaseEnum<string>
 */
final class PaymentMethod extends BaseEnum
{
    const STRIPE = 'stripe';

    const PAYPAL = 'paypal';

    const BANK_TRANSFER = 'bank_transfer';

    const CREDIT_CARD = 'credit_card';

    const BALANCE = 'balance';

    const MIXED = 'mixed';

    /**
     * Get the icon for the payment method
     *
     * @param  string  $method
     */
    public static function icon($method): string
    {
        return match ($method) {
            self::STRIPE => 'stripe-icon',
            self::PAYPAL => 'paypal-icon',
            self::BANK_TRANSFER => 'bank-icon',
            self::CREDIT_CARD => 'credit-card-icon',
            self::BALANCE => 'balance-icon',
            self::MIXED => 'mixed-icon',
            default => 'payment-icon',
        };
    }

    /**
     * Check if the payment method is an online payment
     *
     * @param  string  $method
     */
    public static function isOnlinePayment($method): bool
    {
        return in_array($method, [self::STRIPE, self::PAYPAL, self::CREDIT_CARD], true);
    }
}
