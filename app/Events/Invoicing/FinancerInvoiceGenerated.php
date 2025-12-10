<?php

declare(strict_types=1);

namespace App\Events\Invoicing;

use Illuminate\Support\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class FinancerInvoiceGenerated extends ShouldBeStored
{
    public function __construct(
        public string $financerId,
        public string $invoiceId,
        public int $amountTtc,
        public Carbon $generatedAt,
    ) {}
}
