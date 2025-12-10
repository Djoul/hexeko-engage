<?php

declare(strict_types=1);

namespace App\Events\Invoicing;

use Illuminate\Support\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class DivisionInvoiceGenerated extends ShouldBeStored
{
    public function __construct(
        public string $divisionId,
        public string $invoiceId,
        public int $amountTtc,
        public Carbon $generatedAt,
    ) {}
}
