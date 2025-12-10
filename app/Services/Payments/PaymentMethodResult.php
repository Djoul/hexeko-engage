<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class PaymentMethodResult
{
    public function __construct(
        public string $method,
        public float $balanceAmount,
        public float $stripeAmount
    ) {}
}
