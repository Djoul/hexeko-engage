<?php

declare(strict_types=1);

namespace App\Services\Invoicing\Strategies;

class UkVatRateStrategy implements VatRateStrategyInterface
{
    public function supports(string $country): bool
    {
        return strtoupper($country) === 'GB';
    }

    public function getRate(): float
    {
        return 0.20;
    }
}
