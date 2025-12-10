<?php

namespace App\Integrations\Vouchers\Amilon\Traits;

trait OrderItemAccessorsAndHelpers
{
    /**
     * Get the total price for this order item.
     */
    public function getTotalPrice(): float
    {
        return $this->price * $this->quantity;
    }

    /**
     * Check if this order item has vouchers.
     */
    public function hasVouchers(): bool
    {
        return ! empty($this->vouchers);
    }

    /**
     * Get the count of vouchers for this order item.
     */
    public function getVoucherCount(): int
    {
        return is_array($this->vouchers) ? count($this->vouchers) : 0;
    }

    /**
     * Add a voucher to this order item.
     *
     * @param  array<string, mixed>  $voucher
     */
    public function addVoucher(array $voucher): void
    {
        /** @var array<string, mixed> $vouchers */
        $vouchers = $this->vouchers ?? [];
        if (! is_array($vouchers)) {
            $vouchers = [];
        }
        $count = count($vouchers);
        $vouchers[(string) $count] = $voucher;
        /** @var array<string, mixed> $vouchersAssigned */
        $vouchersAssigned = $vouchers;
        $this->vouchers = $vouchersAssigned;
    }

    /**
     * Set vouchers for this order item.
     *
     * @param  array<string, mixed>  $vouchers
     */
    public function setVouchers(array $vouchers): void
    {
        /** @var array<string, mixed> $vouchers */
        $this->vouchers = $vouchers;
    }
}
