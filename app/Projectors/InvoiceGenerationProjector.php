<?php

declare(strict_types=1);

namespace App\Projectors;

use App\Events\Invoicing\InvoiceBatchCompleted;
use App\Events\Invoicing\InvoiceBatchStarted;
use App\Events\Invoicing\InvoiceCompleted;
use App\Events\Invoicing\InvoiceFailed;
use App\Models\InvoiceGenerationBatch;
use Illuminate\Support\Str;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class InvoiceGenerationProjector extends Projector
{
    public function onInvoiceBatchStarted(InvoiceBatchStarted $event): void
    {
        InvoiceGenerationBatch::updateOrCreate(
            ['batch_id' => $event->batchId],
            [
                'id' => (string) Str::uuid(),
                'month_year' => $event->monthYear,
                'total_invoices' => $event->totalInvoices,
                'completed_count' => 0,
                'failed_count' => 0,
                'status' => 'in_progress',
                'started_at' => $event->startedAt,
                'completed_at' => null,
            ]
        );
    }

    public function onInvoiceCompleted(InvoiceCompleted $event): void
    {
        InvoiceGenerationBatch::where('batch_id', $event->batchId)->increment('completed_count');
    }

    public function onInvoiceFailed(InvoiceFailed $event): void
    {
        InvoiceGenerationBatch::where('batch_id', $event->batchId)->increment('failed_count', 1, [
            'last_error' => $event->error,
        ]);
    }

    public function onInvoiceBatchCompleted(InvoiceBatchCompleted $event): void
    {
        InvoiceGenerationBatch::where('batch_id', $event->batchId)->update([
            'status' => $event->status,
            'completed_at' => $event->completedAt,
        ]);
    }
}
