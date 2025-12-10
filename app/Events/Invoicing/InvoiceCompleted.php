<?php

declare(strict_types=1);

namespace App\Events\Invoicing;

use Illuminate\Support\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class InvoiceCompleted extends ShouldBeStored
{
    public function __construct(
        public string $batchId,
        public string $invoiceId,
        public Carbon $completedAt,
    ) {}
}
