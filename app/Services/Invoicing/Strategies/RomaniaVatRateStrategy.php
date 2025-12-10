<?php

declare(strict_types=1);

namespace App\Services\Invoicing\Strategies;

class RomaniaVatRateStrategy implements VatRateStrategyInterface
{
    public function supports(string $country): bool
    {
        return strtoupper($country) === 'RO';
    }

    public function getRate(): float
    {
        return 0.19;
    }
}
