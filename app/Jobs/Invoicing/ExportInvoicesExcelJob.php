<?php

declare(strict_types=1);

namespace App\Jobs\Invoicing;

use App\Actions\Invoicing\ExportInvoicesExcelAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExportInvoicesExcelJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public readonly array $filters = [],
        public readonly ?string $email = null,
    ) {
        $this->queue = config('invoicing.export.queue', $this->queue);
    }

    public function handle(ExportInvoicesExcelAction $action): void
    {
        $action->execute($this->filters);
    }
}
