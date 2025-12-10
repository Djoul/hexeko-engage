<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class PaymentResult
{
    public function __construct(
        public bool $success,
        public float $amountDebited,
        public string $transactionId,
        public float $remainingBalance,
        public ?string $errorMessage = null
    ) {}
}
