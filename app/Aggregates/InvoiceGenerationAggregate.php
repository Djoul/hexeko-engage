<?php

declare(strict_types=1);

namespace App\Aggregates;

use App\Events\Invoicing\InvoiceBatchCompleted;
use App\Events\Invoicing\InvoiceBatchStarted;
use App\Events\Invoicing\InvoiceCompleted;
use App\Events\Invoicing\InvoiceFailed;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class InvoiceGenerationAggregate extends AggregateRoot
{
    /**
     * @var array<string, array{month_year:string,total:int,completed:int,failed:int,status:string,started_at:?Carbon,completed_at:?Carbon}>
     */
    protected array $batches = [];

    public function batchStarted(string $batchId, string $monthYear, int $totalInvoices, Carbon $startedAt): self
    {
        $this->recordThat(new InvoiceBatchStarted(
            batchId: $batchId,
            monthYear: $monthYear,
            totalInvoices: $totalInvoices,
            startedAt: $startedAt,
        ));

        return $this;
    }

    public function invoiceCompleted(string $batchId, string $invoiceId, ?Carbon $completedAt = null): self
    {
        $this->ensureBatchExists($batchId);

        $this->recordThat(new InvoiceCompleted(
            batchId: $batchId,
            invoiceId: $invoiceId,
            completedAt: $completedAt ?? Carbon::now(),
        ));

        return $this;
    }

    public function invoiceFailed(string $batchId, string $invoiceId, string $error, ?Carbon $failedAt = null): self
    {
        $this->ensureBatchExists($batchId);

        $this->recordThat(new InvoiceFailed(
            batchId: $batchId,
            invoiceId: $invoiceId,
            error: $error,
            failedAt: $failedAt ?? Carbon::now(),
        ));

        return $this;
    }

    public function batchCompleted(string $batchId, Carbon $completedAt): self
    {
        $this->ensureBatchExists($batchId);

        $status = $this->determineStatus($batchId);

        $this->recordThat(new InvoiceBatchCompleted(
            batchId: $batchId,
            status: $status,
            completedAt: $completedAt,
        ));

        return $this;
    }

    protected function applyInvoiceBatchStarted(InvoiceBatchStarted $event): void
    {
        $this->batches[$event->batchId] = [
            'month_year' => $event->monthYear,
            'total' => $event->totalInvoices,
            'completed' => 0,
            'failed' => 0,
            'status' => 'in_progress',
            'started_at' => $event->startedAt,
            'completed_at' => null,
        ];
    }

    protected function applyInvoiceCompleted(InvoiceCompleted $event): void
    {
        $this->ensureBatchExists($event->batchId);
        $this->batches[$event->batchId]['completed']++;
    }

    protected function applyInvoiceFailed(InvoiceFailed $event): void
    {
        $this->ensureBatchExists($event->batchId);
        $this->batches[$event->batchId]['failed']++;
    }

    protected function applyInvoiceBatchCompleted(InvoiceBatchCompleted $event): void
    {
        $this->ensureBatchExists($event->batchId);
        $this->batches[$event->batchId]['status'] = $event->status;
        $this->batches[$event->batchId]['completed_at'] = $event->completedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function getGenerationStatus(string $batchId): array
    {
        return $this->batches[$batchId] ?? [];
    }

    protected function determineStatus(string $batchId): string
    {
        $state = $this->batches[$batchId] ?? null;

        if ($state === null) {
            return 'unknown';
        }

        if ($state['failed'] > 0) {
            if ($state['completed'] === 0) {
                return 'failed';
            }

            return 'completed_with_errors';
        }

        if ($state['completed'] >= $state['total']) {
            return 'completed';
        }

        return 'in_progress';
    }

    protected function ensureBatchExists(string $batchId): void
    {
        if (! array_key_exists($batchId, $this->batches)) {
            throw new InvalidArgumentException("Batch [{$batchId}] has not been started.");
        }
    }
}
