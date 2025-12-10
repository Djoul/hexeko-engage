<?php

declare(strict_types=1);

namespace App\Services\Invoicing\Strategies;

use LogicException;

class VatRateStrategyFactory
{
    /**
     * @var array<int, VatRateStrategyInterface>
     */
    private array $strategies;

    public function __construct(
        FrenchVatRateStrategy $frenchVatRateStrategy,
        BelgiumVatRateStrategy $belgiumVatRateStrategy,
        PortugueseVatRateStrategy $portugueseVatRateStrategy,
        LuxembourgVatRateStrategy $luxembourgVatRateStrategy,
        UkVatRateStrategy $ukVatRateStrategy,
        RomaniaVatRateStrategy $romaniaVatRateStrategy,
        FallbackVatRateStrategy $fallbackVatRateStrategy,
    ) {
        $this->strategies = [
            $frenchVatRateStrategy,
            $belgiumVatRateStrategy,
            $portugueseVatRateStrategy,
            $luxembourgVatRateStrategy,
            $ukVatRateStrategy,
            $romaniaVatRateStrategy,
            $fallbackVatRateStrategy,
        ];
    }

    public function getStrategy(string $country): VatRateStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($country)) {
                return $strategy;
            }
        }

        // This should never happen as FallbackVatRateStrategy always returns true
        throw new LogicException('No VAT strategy found. FallbackVatRateStrategy should always match.');
    }
}
