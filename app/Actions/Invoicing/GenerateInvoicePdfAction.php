<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Services\Invoicing\InvoicePdfCacheService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerateInvoicePdfAction
{
    public function __construct(
        private readonly InvoicePdfCacheService $cacheService,
    ) {}

    public function execute(string $invoiceId, bool $forceRegenerate = false): StreamedResponse
    {
        $result = $this->cacheService->get($invoiceId, $forceRegenerate);
        $fileName = sprintf('invoice-%s.pdf', $result->invoice->invoice_number);

        return response()->streamDownload(static function () use ($result): void {
            echo $result->content;
        }, $fileName, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
