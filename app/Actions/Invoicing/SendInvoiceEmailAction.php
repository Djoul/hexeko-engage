<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Services\Invoicing\InvoicePdfCacheService;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendInvoiceEmailAction
{
    public function __construct(
        private readonly InvoicePdfCacheService $cacheService,
        private readonly GenerateInvoiceExcelExportsAction $excelExportsAction,
    ) {}

    /**
     * @param  array<int, string>  $cc
     */
    public function execute(
        string $invoiceId,
        string $email,
        array $cc = [],
        bool $includeUserBilling = true,
        bool $includeModuleActivation = true
    ): void {
        $result = $this->cacheService->get($invoiceId, false);
        $queueConfig = config('invoicing.emails.queue', config('queue.default'));

        /** @var string|null $queue */
        $queue = is_string($queueConfig) ? $queueConfig : null;

        // Generate Excel exports if requested
        $excelExports = [];
        if ($includeUserBilling || $includeModuleActivation) {
            $excelExports = $this->excelExportsAction->execute(
                invoiceId: $invoiceId,
                includeUserBilling: $includeUserBilling,
                includeModuleActivation: $includeModuleActivation
            );
        }

        $mailable = (new InvoiceMail($result->invoice, $result->content, $excelExports))
            ->onQueue($queue);

        $mailer = Mail::to($email);

        if ($cc !== []) {
            $mailer->cc($cc);
        }

        $mailer->queue($mailable);

        Invoice::where('id', $invoiceId)->update([
            'sent_at' => Date::now(),
        ]);

        Log::info('Invoice email queued', [
            'invoice_id' => $invoiceId,
            'recipient' => $email,
            'cc' => $cc,
            'excel_exports' => array_keys($excelExports),
        ]);
    }
}
