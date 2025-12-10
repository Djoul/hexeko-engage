<?php

declare(strict_types=1);

namespace App\Services\Invoicing\Strategies;

class PortugueseVatRateStrategy implements VatRateStrategyInterface
{
    public function supports(string $country): bool
    {
        return strtoupper($country) === 'PT';
    }

    public function getRate(): float
    {
        return 0.23;
    }
}
