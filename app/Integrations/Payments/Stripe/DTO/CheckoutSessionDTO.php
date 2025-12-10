<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\DTO;

readonly class CheckoutSessionDTO
{
    public function __construct(
        public string $userId,
        public float $amount,
        public string $currency,
        public string $creditType,
        public int $creditAmount,
        public string $successUrl,
        public string $cancelUrl,
        public string $productName,
    ) {}

    public function getAmountInCents(): int
    {
        return (int) ($this->amount * 100);
    }
}
