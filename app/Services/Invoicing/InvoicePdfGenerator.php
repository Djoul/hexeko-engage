<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\Invoice;
use Dompdf\Dompdf;
use Illuminate\Contracts\View\Factory as ViewFactory;

class InvoicePdfGenerator
{
    public function __construct(
        private readonly ViewFactory $viewFactory,
    ) {}

    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'recipient']);

        $html = $this->viewFactory->make('invoices.pdf', [
            'invoice' => $invoice,
        ])->render();

        $dompdf = new Dompdf([
            'isRemoteEnabled' => true,
        ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        /** @var string $output */
        $output = $dompdf->output();

        return $output;
    }
}
