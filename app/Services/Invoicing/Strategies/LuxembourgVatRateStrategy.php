<?php

declare(strict_types=1);

namespace App\Services\Invoicing\Strategies;

class LuxembourgVatRateStrategy implements VatRateStrategyInterface
{
    public function supports(string $country): bool
    {
        return strtoupper($country) === 'LU';
    }

    public function getRate(): float
    {
        return 0.17;
    }
}
