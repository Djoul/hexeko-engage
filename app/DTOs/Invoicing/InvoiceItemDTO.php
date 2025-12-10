<?php

declare(strict_types=1);

namespace App\DTOs\Invoicing;

use App\Enums\InvoiceItemType;
use App\Models\InvoiceItem;

class InvoiceItemDTO
{
    /**
     * @param  array<string, string|null>  $label
     * @param  array<string, string|null>|null  $description
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $itemType,
        public readonly ?string $moduleId,
        public readonly array $label,
        public readonly ?array $description,
        public readonly int $quantity,
        public readonly ?int $beneficiariesCount,
        public readonly InvoiceAmountsDTO $amounts,
        public readonly ?ProrataCalculationDTO $prorata,
        public readonly array $metadata,
    ) {}

    public static function fromModel(InvoiceItem $item, ?ProrataCalculationDTO $prorata = null, string $currency = 'EUR'): self
    {
        $amounts = InvoiceAmountsDTO::fromItem($item, $currency);

        $label = $item->getTranslations('label');
        $description = $item->getTranslations('description');

        return new self(
            id: $item->id,
            itemType: $item->item_type ?? InvoiceItemType::MODULE,
            moduleId: $item->module_id,
            label: $label === [] ? (is_array($item->label) ? $item->label : []) : $label,
            description: $description === [] ? (is_array($item->description) ? $item->description : null) : $description,
            quantity: $item->quantity,
            beneficiariesCount: $item->beneficiaries_count,
            amounts: $amounts,
            prorata: $prorata,
            metadata: $item->metadata ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'item_type' => $this->itemType,
            'module_id' => $this->moduleId,
            'label' => $this->label,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'beneficiaries_count' => $this->beneficiariesCount,
            'amounts' => $this->amounts->toArray(),
            'prorata' => $this->prorata?->toArray(),
            'metadata' => $this->metadata,
        ];
    }
}
