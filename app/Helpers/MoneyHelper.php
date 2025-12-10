<?php

declare(strict_types=1);

namespace App\Helpers;

use InvalidArgumentException;

/**
 * Helper class for currency conversions between euros and cents.
 *
 * All monetary amounts in the database are stored in CENTS (integer)
 * to avoid floating point precision issues.
 *
 * External APIs (like Amilon) typically return amounts in EUROS (float).
 * Payment providers (like Stripe) expect amounts in CENTS (integer).
 */
class MoneyHelper
{
    /**
     * Convert euros to cents.
     *
     * @param  float  $euros  The amount in euros
     * @return int The amount in cents
     */
    public static function eurosToCents(float $euros): int
    {
        return (int) round($euros * 100);
    }

    /**
     * Convert cents to euros.
     *
     * @param  int  $cents  The amount in cents
     * @return float The amount in euros
     */
    public static function centsToEuros(int $cents): float
    {
        return $cents / 100;
    }

    /**
     * Format cents as a euro string with 2 decimal places.
     *
     * @param  int  $cents  The amount in cents
     * @param  string  $symbol  The currency symbol (default: '€')
     * @return string The formatted amount (e.g., "€10.50")
     */
    public static function formatCentsAsEuros(int $cents, string $symbol = '€'): string
    {
        $euros = self::centsToEuros($cents);

        return $symbol.number_format($euros, 2, '.', '');
    }

    /**
     * Parse a string amount to cents, handling various formats.
     *
     * @param  string|float|int  $amount  The amount in various formats
     * @return int The amount in cents
     */
    public static function parseAmountToCents(string|float|int $amount): int
    {
        if (is_int($amount)) {
            // Assume already in cents if integer
            return $amount;
        }

        if (is_string($amount)) {
            // Remove currency symbols and spaces
            $amount = preg_replace('/[^0-9.,\-]/', '', $amount);
            // Replace comma with dot for decimal separator
            $amount = str_replace(',', '.', (string) $amount);
        }

        return self::eurosToCents((float) $amount);
    }

    /**
     * Check if an amount in cents is valid (positive).
     *
     * @param  int  $cents  The amount in cents
     * @return bool True if valid, false otherwise
     */
    public static function isValidAmount(int $cents): bool
    {
        return $cents > 0;
    }

    /**
     * Calculate the difference between two amounts in cents.
     *
     * @param  int  $totalCents  The total amount in cents
     * @param  int  $paidCents  The paid amount in cents
     * @return int The difference in cents (can be negative)
     */
    public static function calculateDifference(int $totalCents, int $paidCents): int
    {
        return $totalCents - $paidCents;
    }

    /**
     * Apply a percentage discount to an amount in cents.
     *
     * @param  int  $cents  The original amount in cents
     * @param  float  $discountPercentage  The discount percentage (e.g., 20 for 20%)
     * @return int The discounted amount in cents
     */
    public static function applyDiscount(int $cents, float $discountPercentage): int
    {
        if ($discountPercentage < 0 || $discountPercentage > 100) {
            throw new InvalidArgumentException('Discount percentage must be between 0 and 100');
        }

        $discountedAmount = $cents * (1 - $discountPercentage / 100);

        return (int) round($discountedAmount);
    }

    /**
     * Calculate the discount amount from original and discounted prices.
     *
     * @param  int  $originalCents  The original price in cents
     * @param  int  $discountedCents  The discounted price in cents
     * @return int The discount amount in cents
     */
    public static function calculateDiscountAmount(int $originalCents, int $discountedCents): int
    {
        return max(0, $originalCents - $discountedCents);
    }

    /**
     * Calculate the discount percentage from original and discounted prices.
     *
     * @param  int  $originalCents  The original price in cents
     * @param  int  $discountedCents  The discounted price in cents
     * @return float The discount percentage
     */
    public static function calculateDiscountPercentage(int $originalCents, int $discountedCents): float
    {
        if ($originalCents <= 0) {
            return 0.0;
        }

        $discount = $originalCents - $discountedCents;

        return round(($discount / $originalCents) * 100, 2);
    }
}
