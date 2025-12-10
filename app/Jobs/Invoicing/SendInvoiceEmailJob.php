<?php

declare(strict_types=1);

namespace App\Jobs\Invoicing;

use App\Actions\Invoicing\SendInvoiceEmailAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendInvoiceEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, string>  $cc
     */
    public function __construct(
        public readonly string $invoiceId,
        public readonly string $recipientEmail,
        public readonly array $cc = [],
    ) {
        $this->queue = config('invoicing.emails.queue', $this->queue);
    }

    public function handle(SendInvoiceEmailAction $action): void
    {
        $action->execute($this->invoiceId, $this->recipientEmail, $this->cc);
    }
}
