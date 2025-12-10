<?php

declare(strict_types=1);

namespace App\Integrations\Vouchers\Amilon\Enums;

enum VoucherNotificationStatus: string
{
    case ORDER_CREATED = 'order_created';
    case ORDER_CREATION_FAILED = 'order_creation_failed';
    case VOUCHERS_RECEIVED = 'vouchers_received';
    case AMILON_ORDER_CREATED = 'amilon_order_created';
    case PAYMENT_COMPLETED = 'payment_completed';
    case PAYMENT_FAILED = 'payment_failed';

    public function label(): string
    {
        return match ($this) {
            self::ORDER_CREATED => 'Order Created',
            self::ORDER_CREATION_FAILED => 'Order Creation Failed',
            self::VOUCHERS_RECEIVED => 'Vouchers Received',
            self::AMILON_ORDER_CREATED => 'Amilon Order Created',
            self::PAYMENT_COMPLETED => 'Payment Completed',
            self::PAYMENT_FAILED => 'Payment Failed',
        };
    }

    public function isSuccess(): bool
    {
        return in_array($this, [
            self::ORDER_CREATED,
            self::VOUCHERS_RECEIVED,
            self::AMILON_ORDER_CREATED,
            self::PAYMENT_COMPLETED,
        ], true);
    }

    public function isError(): bool
    {
        return in_array($this, [
            self::ORDER_CREATION_FAILED,
            self::PAYMENT_FAILED,
        ], true);
    }
}
