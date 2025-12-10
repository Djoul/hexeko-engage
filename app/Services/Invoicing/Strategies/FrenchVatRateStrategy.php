<?php

declare(strict_types=1);

namespace App\Services\Invoicing\Strategies;

class FrenchVatRateStrategy implements VatRateStrategyInterface
{
    public function supports(string $country): bool
    {
        return strtoupper($country) === 'FR';
    }

    public function getRate(): float
    {
        return 0.20;
    }
}
