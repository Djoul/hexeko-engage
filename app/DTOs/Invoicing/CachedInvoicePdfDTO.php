<?php

declare(strict_types=1);

namespace App\DTOs\Invoicing;

use App\Models\Invoice;

class CachedInvoicePdfDTO
{
    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $content,
        public readonly string $path,
        public readonly bool $fromCache,
    ) {}
}
