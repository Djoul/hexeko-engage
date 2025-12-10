<?php

declare(strict_types=1);

namespace App\DTOs\Invoicing;

class InvoiceBatchDTO
{
    public function __construct(
        public readonly string $batchId,
        public readonly string $monthYear,
        public readonly int $totalInvoices,
        public readonly string $status,
    ) {}

    /**
     * @return array{batch_id: string, month_year: string, total_invoices: int, status: string}
     */
    public function toArray(): array
    {
        return [
            'batch_id' => $this->batchId,
            'month_year' => $this->monthYear,
            'total_invoices' => $this->totalInvoices,
            'status' => $this->status,
        ];
    }
}
