<?php

declare(strict_types=1);

namespace App\Jobs\Invoicing;

use App\Actions\Invoicing\GenerateFinancerInvoiceAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateFinancerInvoiceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $backoff;

    public function __construct(
        public readonly string $financerId,
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
        return $this->financerId.'::'.$this->monthYear;
    }

    public function handle(GenerateFinancerInvoiceAction $action): void
    {
        $action->execute($this->financerId, $this->monthYear, $this->batchId);

        Log::info('Financer invoice generation completed', [
            'financer_id' => $this->financerId,
            'batch_id' => $this->batchId,
            'month_year' => $this->monthYear,
        ]);
    }
}
