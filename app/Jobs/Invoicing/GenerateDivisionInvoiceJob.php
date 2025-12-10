<?php

declare(strict_types=1);

namespace App\Jobs\Invoicing;

use App\Actions\Invoicing\GenerateDivisionInvoiceAction;
use App\Aggregates\InvoiceGenerationAggregate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateDivisionInvoiceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $backoff;

    public function __construct(
        public readonly string $divisionId,
        public readonly string $monthYear,
        public readonly string $batchId,
    ) {
        $config = (array) config('invoicing.generation', []);
        $this->queue = $config['queue'] ?? $this->queue;
        $this->tries = (int) ($config['retry_attempts'] ?? 3);
        $this->backoff = (int) ($config['retry_backoff'] ?? 60);
    }

    public function uniqueId(): string
    {
        return $this->divisionId.'::'.$this->monthYear;
    }

    public function handle(GenerateDivisionInvoiceAction $action): void
    {
        try {
            $action->execute($this->divisionId, $this->monthYear, $this->batchId);
            Log::info('Division invoice generation completed', [
                'division_id' => $this->divisionId,
                'batch_id' => $this->batchId,
                'month_year' => $this->monthYear,
            ]);
        } catch (Throwable $exception) {
            InvoiceGenerationAggregate::retrieve($this->batchId)
                ->invoiceFailed($this->batchId, $this->divisionId, $exception->getMessage())
                ->persist();

            Log::error('Division invoice generation failed', [
                'division_id' => $this->divisionId,
                'batch_id' => $this->batchId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
