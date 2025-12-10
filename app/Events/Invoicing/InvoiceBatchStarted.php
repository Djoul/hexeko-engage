<?php

declare(strict_types=1);

namespace App\Events\Invoicing;

use Illuminate\Support\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class InvoiceBatchStarted extends ShouldBeStored
{
    public function __construct(
        public string $batchId,
        public string $monthYear,
        public int $totalInvoices,
        public Carbon $startedAt,
    ) {}
}
