<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\DTOs\Invoicing\CreateInvoiceDTO;
use App\DTOs\Invoicing\InvoiceItemDTO;
use App\Services\Invoicing\CalculateInvoiceService;
use Illuminate\Support\Str;

class CalculateInvoiceItemsAction
{
    public function __construct(private readonly CalculateInvoiceService $invoiceService) {}

    /**
     * @param  array<int, array<string, mixed>>  $metadata
     * @return array<int, InvoiceItemDTO>
     */
    public function execute(CreateInvoiceDTO $dto, string $country, array $metadata = []): array
    {
        $items = [];

        foreach ($dto->items as $index => $item) {
            $amounts = $this->invoiceService->calculateItemAmounts($item, $country);

            $meta = $metadata[$index] ?? [];

            /** @var array<string, string|null> $label */
            $label = $meta['label'] ?? [];

            /** @var array<string, string|null>|null $description */
            $description = $meta['description'] ?? null;

            if (is_string($label)) {
                $label = ['en' => $label];
            }

            if (is_string($description)) {
                $description = ['en' => $description];
            }

            /** @var string $id */
            $id = $meta['id'] ?? Str::uuid()->toString();

            /** @var int|null $beneficiariesCount */
            $beneficiariesCount = array_key_exists('beneficiaries_count', $meta) && is_int($meta['beneficiaries_count'])
                ? $meta['beneficiaries_count']
                : null;

            /** @var array<string, mixed> $itemMetadata */
            $itemMetadata = array_key_exists('metadata', $meta) && is_array($meta['metadata'])
                ? $meta['metadata']
                : [];

            $items[] = new InvoiceItemDTO(
                id: $id,
                itemType: $item->itemType,
                moduleId: $item->moduleId,
                label: $label,
                description: $description,
                quantity: $item->quantity,
                beneficiariesCount: $beneficiariesCount,
                amounts: $amounts,
                prorata: $item->prorata,
                metadata: $itemMetadata,
            );
        }

        return $items;
    }
}
