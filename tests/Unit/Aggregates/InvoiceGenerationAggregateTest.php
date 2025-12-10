<?php

declare(strict_types=1);

namespace Tests\Unit\Aggregates;

use App\Aggregates\InvoiceGenerationAggregate;
use App\Models\InvoiceGenerationBatch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('event-sourcing')]
class InvoiceGenerationAggregateTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_initializes_batch_state_on_start(): void
    {
        $batchId = Str::uuid()->toString();
        $monthYear = '2025-05';
        $startedAt = Carbon::parse('2025-05-01 07:00:00');

        InvoiceGenerationAggregate::retrieve($batchId)
            ->batchStarted($batchId, $monthYear, 10, $startedAt)
            ->persist();

        $this->assertDatabaseHas('invoice_generation_batches', [
            'batch_id' => $batchId,
            'month_year' => $monthYear,
            'total_invoices' => 10,
            'status' => 'in_progress',
        ]);

        $batch = InvoiceGenerationBatch::where('batch_id', $batchId)->firstOrFail();
        $this->assertTrue($startedAt->equalTo($batch->started_at));
        $this->assertSame(0, $batch->completed_count);
        $this->assertSame(0, $batch->failed_count);

        $status = InvoiceGenerationAggregate::retrieve($batchId)->getGenerationStatus($batchId);
        $this->assertSame(10, $status['total']);
        $this->assertSame(0, $status['completed']);
        $this->assertSame(0, $status['failed']);
    }

    #[Test]
    public function it_tracks_successful_and_failed_invoices(): void
    {
        $batchId = Str::uuid()->toString();

        InvoiceGenerationAggregate::retrieve($batchId)
            ->batchStarted($batchId, '2025-06', 3, Carbon::parse('2025-06-01 09:00:00'))
            ->persist();

        InvoiceGenerationAggregate::retrieve($batchId)
            ->invoiceCompleted($batchId, 'inv-1')
            ->persist();

        InvoiceGenerationAggregate::retrieve($batchId)
            ->invoiceFailed($batchId, 'inv-2', 'Insufficient data')
            ->persist();

        $batch = InvoiceGenerationBatch::where('batch_id', $batchId)->firstOrFail();
        $this->assertSame(1, $batch->completed_count);
        $this->assertSame(1, $batch->failed_count);
        $this->assertSame('in_progress', $batch->status);

        $status = InvoiceGenerationAggregate::retrieve($batchId)->getGenerationStatus($batchId);
        $this->assertSame(1, $status['completed']);
        $this->assertSame(1, $status['failed']);
    }

    #[Test]
    public function it_marks_batch_completed(): void
    {
        $batchId = Str::uuid()->toString();
        $completedAt = Carbon::parse('2025-07-10 12:00:00');

        InvoiceGenerationAggregate::retrieve($batchId)
            ->batchStarted($batchId, '2025-07', 2, Carbon::parse('2025-07-01 08:00:00'))
            ->persist();

        InvoiceGenerationAggregate::retrieve($batchId)
            ->invoiceCompleted($batchId, 'inv-10')
            ->persist();

        InvoiceGenerationAggregate::retrieve($batchId)
            ->invoiceCompleted($batchId, 'inv-11')
            ->persist();

        InvoiceGenerationAggregate::retrieve($batchId)
            ->batchCompleted($batchId, $completedAt)
            ->persist();

        $batch = InvoiceGenerationBatch::where('batch_id', $batchId)->firstOrFail();
        $this->assertSame('completed', $batch->status);
        $this->assertTrue($completedAt->equalTo($batch->completed_at));

        $status = InvoiceGenerationAggregate::retrieve($batchId)->getGenerationStatus($batchId);
        $this->assertSame('completed', $status['status']);
    }
}
