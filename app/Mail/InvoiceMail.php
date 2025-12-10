<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, string>  $excelExports  ['user_billing' => 'content', 'module_activation' => 'content']
     */
    public function __construct(
        public Invoice $invoice,
        public string $pdfContent,
        public array $excelExports = [],
    ) {}

    public function build(): self
    {
        $fileName = sprintf('invoice-%s.pdf', $this->invoice->invoice_number);

        $mail = $this
            ->subject(sprintf('Invoice %s', $this->invoice->invoice_number))
            ->view('emails.invoices.invoice')
            ->with([
                'invoice' => $this->invoice,
                'hasExcelAttachments' => $this->excelExports !== [],
            ])
            ->attachData($this->pdfContent, $fileName, [
                'mime' => 'application/pdf',
            ]);

        // Attach Excel exports if provided
        foreach ($this->excelExports as $type => $content) {
            $excelFileName = sprintf(
                'invoice-%s-%s.xlsx',
                $this->invoice->invoice_number,
                $type
            );

            $mail->attachData($content, $excelFileName, [
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        return $mail;
    }
}
