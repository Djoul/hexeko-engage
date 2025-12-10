<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\DTOs\Invoicing\CreateInvoiceItemDTO;
use App\DTOs\Invoicing\InvoiceAmountsDTO;
use InvalidArgumentException;

class CalculateInvoiceService
{
    private const CURRENCY = 'EUR';

    public function __construct(
        private readonly VatCalculatorService $vatCalculatorService,
    ) {}

    public function calculateItemAmounts(CreateInvoiceItemDTO $item, string $country): InvoiceAmountsDTO
    {
        $subtotal = $this->calculateSubtotal($item);

        if ($subtotal === 0) {
            return new InvoiceAmountsDTO(0, 0, 0, self::CURRENCY);
        }

        $vatAmount = $this->vatCalculatorService->calculateVat($subtotal, $country);
        $totalTtc = $this->vatCalculatorService->calculateTotalTtc($subtotal, $vatAmount);

        return new InvoiceAmountsDTO($subtotal, $vatAmount, $totalTtc, self::CURRENCY);
    }

    /**
     * @param  array<int, CreateInvoiceItemDTO>  $items
     */
    public function calculateInvoiceAmounts(array $items, string $country): InvoiceAmountsDTO
    {
        $subtotal = 0;
        $vatAmount = 0;
        $totalTtc = 0;

        foreach ($items as $item) {
            if (! $item instanceof CreateInvoiceItemDTO) {
                throw new InvalidArgumentException('Invoice items must be instances of CreateInvoiceItemDTO.');
            }

            $amount = $this->calculateItemAmounts($item, $country);
            $subtotal += $amount->subtotalHtva;
            $vatAmount += $amount->vatAmount;
            $totalTtc += $amount->totalTtc;
        }

        return new InvoiceAmountsDTO($subtotal, $vatAmount, $totalTtc, self::CURRENCY);
    }

    private function calculateSubtotal(CreateInvoiceItemDTO $item): int
    {
        $unitTimesQuantity = bcmul((string) $item->unitPriceHtva, (string) $item->quantity, 4);
        $prorata = bcmul($unitTimesQuantity, $this->formatPercentage($item->prorataPercentage), 4);

        return $this->toInteger($prorata);
    }

    private function formatPercentage(float $percentage): string
    {
        return number_format($percentage, 4, '.', '');
    }

    private function toInteger(string $value): int
    {
        if ($value === '') {
            return 0;
        }

        $rounded = bcadd($value, '0', 0);

        return (int) $rounded;
    }
}
