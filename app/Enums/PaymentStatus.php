<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * @extends BaseEnum<string>
 */
final class PaymentStatus extends BaseEnum
{
    const PENDING = 'pending';

    const COMPLETED = 'completed';

    const FAILED = 'failed';

    const PROCESSING = 'processing';

    const CANCELLED = 'cancelled';

    /**
     * Check if the payment status is successful
     *
     * @param  string  $status
     */
    public static function isSuccessful($status): bool
    {
        return $status === self::COMPLETED;
    }

    /**
     * Check if the payment status is failed
     *
     * @param  string  $status
     */
    public static function isFailed($status): bool
    {
        return in_array($status, [self::FAILED, self::CANCELLED], true);
    }

    /**
     * Check if the payment status is pending
     *
     * @param  string  $status
     */
    public static function isPending($status): bool
    {
        return in_array($status, [self::PENDING, self::PROCESSING], true);
    }
}
