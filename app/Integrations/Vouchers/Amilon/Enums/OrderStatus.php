<?php

declare(strict_types=1);

namespace App\Integrations\Vouchers\Amilon\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * @method static static PENDING()
 * @method static static CONFIRMED()
 * @method static static DELIVERED()
 * @method static static ERROR()
 * @method static static PROCESSING()
 * @method static static CANCELLED()
 */
final class OrderStatus extends Enum implements LocalizedEnum
{
    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    /**
     * @deprecated Use CONFIRMED instead. Will be removed in future version.
     */
    public const DELIVERED = 'delivered';

    public const ERROR = 'error';

    /**
     * @deprecated Use PENDING instead. Will be removed in future version.
     */
    public const PROCESSING = 'processing';

    public const CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this->value) {
            self::PENDING => 'Pending',
            self::CONFIRMED => 'Confirmed',
            self::DELIVERED => 'Delivered',
            self::ERROR => 'Error',
            self::PROCESSING => 'Processing',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function isCompleted(): bool
    {
        return $this->value === self::CONFIRMED;
    }

    public function isFailed(): bool
    {
        return in_array($this->value, [self::ERROR, self::CANCELLED], true);
    }

    public function isPending(): bool
    {
        return $this->value === self::PENDING;
    }

    public static function fromAmilonStatus(?string $status): self
    {
        if ($status === null) {
            return self::PENDING();
        }

        return match (strtolower($status)) {
            'pending', 'processing', 'in progress' => self::PENDING(),
            'confirmed', 'completed', 'delivered', 'success' => self::CONFIRMED(),
            'cancelled' => self::CANCELLED(),
            'error', 'failed' => self::ERROR(),
            default => self::PENDING(),
        };
    }
}
