<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Services\Invoicing\Strategies\VatRateStrategyFactory;

class VatCalculatorService
{
    public function __construct(private readonly VatRateStrategyFactory $factory) {}

    public function calculateVat(int $amountHtva, string $country): int
    {
        $rate = $this->getVatRate($country);
        /** @var numeric-string $amountStr */
        $amountStr = (string) $amountHtva;
        /** @var numeric-string $rateStr */
        $rateStr = $this->formatRate($rate);
        $rawVat = bcmul($amountStr, $rateStr, 4);

        return $this->roundToInteger($rawVat);
    }

    public function getVatRate(string $country): float
    {
        return $this->factory->getStrategy($country)->getRate();
    }

    public function calculateTotalTtc(int $amountHtva, int $vatAmount): int
    {
        return $amountHtva + $vatAmount;
    }

    private function formatRate(float $rate): string
    {
        return number_format($rate, 4, '.', '');
    }

    private function roundToInteger(string $value): int
    {
        /** @var numeric-string $zero */
        $zero = '0';

        return (int) bcadd($value, $zero, 0);
    }
}
