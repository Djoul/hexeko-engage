<?php

declare(strict_types=1);

namespace App\Services\Payments;

use InvalidArgumentException;

class PaymentMethodSelector
{
    public function determinePaymentMethod(
        float $orderAmount,
        float $userBalance
    ): PaymentMethodResult {
        if ($orderAmount < 0) {
            throw new InvalidArgumentException('Order amount cannot be negative');
        }

        // Treat negative balance as zero
        $effectiveBalance = max(0, $userBalance);

        // Use bccomp for precise decimal comparison
        if (bccomp((string) $effectiveBalance, (string) $orderAmount, 2) >= 0) {
            return new PaymentMethodResult(
                method: 'balance',
                balanceAmount: $orderAmount,
                stripeAmount: 0.00
            );
        }

        if ($effectiveBalance > 0) {
            $stripeAmount = (float) bcsub((string) $orderAmount, (string) $effectiveBalance, 2);

            return new PaymentMethodResult(
                method: 'mixed',
                balanceAmount: $effectiveBalance,
                stripeAmount: $stripeAmount
            );
        }

        return new PaymentMethodResult(
            method: 'stripe',
            balanceAmount: 0.00,
            stripeAmount: $orderAmount
        );
    }
}
