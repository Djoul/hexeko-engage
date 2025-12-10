<?php

declare(strict_types=1);

namespace App\Aggregates;

use App\Events\Invoicing\DivisionCreditApplied;
use App\Events\Invoicing\DivisionInvoiceGenerated;
use App\Events\Invoicing\DivisionInvoicePaid;
use Illuminate\Support\Carbon;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class DivisionBalanceAggregate extends AggregateRoot
{
    /**
     * @var array<string, array{amount:int,outstanding:int}>
     */
    protected array $invoices = [];

    protected int $balance = 0;

    public function invoiceGenerated(string $divisionId, string $invoiceId, int $amountTtc, Carbon $generatedAt): self
    {
        $this->recordThat(new DivisionInvoiceGenerated(
            divisionId: $divisionId,
            invoiceId: $invoiceId,
            amountTtc: $amountTtc,
            generatedAt: $generatedAt,
        ));

        return $this;
    }

    public function invoicePaid(string $divisionId, string $invoiceId, int $amountPaid, Carbon $paidAt): self
    {
        $this->recordThat(new DivisionInvoicePaid(
            divisionId: $divisionId,
            invoiceId: $invoiceId,
            amountPaid: $amountPaid,
            paidAt: $paidAt,
        ));

        return $this;
    }

    public function creditApplied(string $divisionId, ?string $invoiceId, int $creditAmount, ?string $reason, Carbon $appliedAt): self
    {
        $this->recordThat(new DivisionCreditApplied(
            divisionId: $divisionId,
            invoiceId: $invoiceId,
            creditAmount: $creditAmount,
            reason: $reason,
            appliedAt: $appliedAt,
        ));

        return $this;
    }

    protected function applyDivisionInvoiceGenerated(DivisionInvoiceGenerated $event): void
    {
        $this->balance += $event->amountTtc;
        $this->invoices[$event->invoiceId] = [
            'amount' => $event->amountTtc,
            'outstanding' => $event->amountTtc,
        ];
    }

    protected function applyDivisionInvoicePaid(DivisionInvoicePaid $event): void
    {
        $this->balance -= $event->amountPaid;

        if (isset($this->invoices[$event->invoiceId])) {
            $this->invoices[$event->invoiceId]['outstanding'] -= $event->amountPaid;
            if ($this->invoices[$event->invoiceId]['outstanding'] < 0) {
                $this->invoices[$event->invoiceId]['outstanding'] = 0;
            }
        }
    }

    protected function applyDivisionCreditApplied(DivisionCreditApplied $event): void
    {
        $this->balance -= $event->creditAmount;

        if ($event->invoiceId !== null && isset($this->invoices[$event->invoiceId])) {
            $this->invoices[$event->invoiceId]['outstanding'] -= $event->creditAmount;
            if ($this->invoices[$event->invoiceId]['outstanding'] < 0) {
                $this->invoices[$event->invoiceId]['outstanding'] = 0;
            }
        }
    }

    public function getBalance(): int
    {
        return $this->balance;
    }
}
