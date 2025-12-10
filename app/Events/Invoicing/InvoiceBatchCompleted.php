<?php

declare(strict_types=1);

namespace App\Events\Invoicing;

use Illuminate\Support\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class InvoiceBatchCompleted extends ShouldBeStored
{
    public function __construct(
        public string $batchId,
        public string $status,
        public Carbon $completedAt,
    ) {}
}
