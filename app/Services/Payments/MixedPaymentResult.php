<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * @deprecated
 * Mixed payments are now handled by frontend orchestration
 * 2025-01-05
 */
final readonly class MixedPaymentResult
{
    public function __construct(
        public bool $success,
        public float $balanceAmount,
        public string $stripePaymentIntentId,
        public string $stripeClientSecret,
        public string $paymentMethod = 'mixed',
        public ?string $stripePaymentId = null,
        public ?string $errorMessage = null
    ) {}
}
