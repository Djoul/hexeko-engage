<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Models\Traits;

trait StripePaymentAccessorsAndHelpers
{
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
            'processed_at' => now(),
        ]);
    }

    public function markAsCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
            'processed_at' => now(),
        ]);
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format((float) $this->amount, 2).' '.strtoupper($this->currency);
    }

    public function getProductNameAttribute(): ?string
    {
        return $this->metadata['product_name'] ?? null;
    }
}
