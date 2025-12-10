<?php

declare(strict_types=1);

namespace App\Services\Invoicing\Strategies;

interface VatRateStrategyInterface
{
    public function supports(string $country): bool;

    public function getRate(): float;
}
