<?php

declare(strict_types=1);

namespace App\Events\Invoicing;

use Illuminate\Support\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class DivisionCreditApplied extends ShouldBeStored
{
    public function __construct(
        public string $divisionId,
        public ?string $invoiceId,
        public int $creditAmount,
        public ?string $reason,
        public Carbon $appliedAt,
    ) {}
}
