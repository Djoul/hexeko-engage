<?php

declare(strict_types=1);

namespace App\Services\Invoicing\Strategies;

class BelgiumVatRateStrategy implements VatRateStrategyInterface
{
    public function supports(string $country): bool
    {
        return strtoupper($country) === 'BE';
    }

    public function getRate(): float
    {
        return 0.21;
    }
}
