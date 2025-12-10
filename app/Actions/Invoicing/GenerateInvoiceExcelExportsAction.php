<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Exports\ModuleActivationExport;
use App\Exports\UserBillingDetailsExport;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class GenerateInvoiceExcelExportsAction
{
    /**
     * Generate Excel exports for an invoice.
     *
     * @return array<string, string> ['user_billing' => 'content', 'module_activation' => 'content']
     */
    public function execute(
        string $invoiceId,
        bool $includeUserBilling = true,
        bool $includeModuleActivation = true
    ): array {
        $invoice = Invoice::with('recipient')->findOrFail($invoiceId);
        $exports = [];

        // Only generate exports for financer invoices
        if ($invoice->recipient_type !== 'App\\Models\\Financer') {
            Log::info('Skipping Excel exports for non-financer invoice', [
                'invoice_id' => $invoiceId,
                'recipient_type' => $invoice->recipient_type,
            ]);

            return [];
        }

        // Generate User Billing Details Export
        if ($includeUserBilling) {
            $exports['user-billing'] = $this->generateExport(
                new UserBillingDetailsExport($invoiceId)
            );

            Log::info('Generated user billing export', ['invoice_id' => $invoiceId]);
        }

        // Generate Module Activation Export
        if ($includeModuleActivation) {
            $exports['module-activation'] = $this->generateExport(
                new ModuleActivationExport($invoiceId)
            );

            Log::info('Generated module activation export', ['invoice_id' => $invoiceId]);
        }

        return $exports;
    }

    /**
     * Generate Excel export and return raw content.
     */
    private function generateExport(object $export): string
    {
        // Store to temporary file to capture content
        $tempPath = tempnam(sys_get_temp_dir(), 'invoice_export_');

        Excel::store(
            $export,
            $tempPath,
            'local',
            \Maatwebsite\Excel\Excel::XLSX
        );

        // Read content from temp file
        $content = file_get_contents($tempPath);

        // Clean up temp file
        @unlink($tempPath);

        if ($content === false) {
            throw new RuntimeException('Failed to generate Excel export');
        }

        return $content;
    }
}
