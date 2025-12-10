<?php

declare(strict_types=1);

namespace App\DTOs\Invoicing;

/**
 * @phpstan-type CreateItemArray array<string, mixed>
 */
class CreateInvoiceDTO
{
    /**
     * @param  array<int, CreateInvoiceItemDTO>  $items
     */
    public function __construct(
        public readonly string $recipientType,
        public readonly string $recipientId,
        public readonly string $billingPeriodStart,
        public readonly string $billingPeriodEnd,
        public readonly array $items,
    ) {}

    /**
     * @return array{
     *     recipient_type: string,
     *     recipient_id: string,
     *     billing_period_start: string,
     *     billing_period_end: string,
     *     items: array<int, array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'recipient_type' => $this->recipientType,
            'recipient_id' => $this->recipientId,
            'billing_period_start' => $this->billingPeriodStart,
            'billing_period_end' => $this->billingPeriodEnd,
            'items' => array_map(
                static fn (CreateInvoiceItemDTO $item): array => $item->toArray(),
                $this->items,
            ),
        ];
    }
}
