<?php

declare(strict_types=1);

namespace App\DTOs\Invoicing;

class CreateInvoiceItemDTO
{
    public function __construct(
        public readonly string $itemType,
        public readonly ?string $moduleId,
        public readonly int $unitPriceHtva,
        public readonly int $quantity,
        public readonly float $prorataPercentage,
        public readonly ?ProrataCalculationDTO $prorata = null,
    ) {}

    /**
     * @return array<string, int|float|string|null|array>
     */
    public function toArray(): array
    {
        return [
            'item_type' => $this->itemType,
            'module_id' => $this->moduleId,
            'unit_price_htva' => $this->unitPriceHtva,
            'quantity' => $this->quantity,
            'prorata_percentage' => $this->prorataPercentage,
            'prorata' => $this->prorata?->toArray(),
        ];
    }
}
