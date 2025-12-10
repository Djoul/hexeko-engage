<?php

declare(strict_types=1);

namespace App\Projectors;

use App\Events\Invoicing\DivisionCreditApplied;
use App\Events\Invoicing\DivisionInvoiceGenerated;
use App\Events\Invoicing\DivisionInvoicePaid;
use App\Models\DivisionBalance;
use Illuminate\Support\Str;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class DivisionBalanceProjector extends Projector
{
    public function onDivisionInvoiceGenerated(DivisionInvoiceGenerated $event): void
    {
        $balance = DivisionBalance::firstOrCreate(
            ['division_id' => $event->divisionId],
            ['id' => (string) Str::uuid(), 'balance' => 0]
        );

        $balance->increment('balance', $event->amountTtc);
        $balance->last_invoice_at = $event->generatedAt;
        $balance->save();
    }

    public function onDivisionInvoicePaid(DivisionInvoicePaid $event): void
    {
        $balance = DivisionBalance::firstOrCreate(
            ['division_id' => $event->divisionId],
            ['id' => (string) Str::uuid(), 'balance' => 0]
        );

        $balance->decrement('balance', $event->amountPaid);
        $balance->last_payment_at = $event->paidAt;
        $balance->save();
    }

    public function onDivisionCreditApplied(DivisionCreditApplied $event): void
    {
        $balance = DivisionBalance::firstOrCreate(
            ['division_id' => $event->divisionId],
            ['id' => (string) Str::uuid(), 'balance' => 0]
        );

        $balance->decrement('balance', $event->creditAmount);
        $balance->last_credit_at = $event->appliedAt;
        $balance->save();
    }
}
