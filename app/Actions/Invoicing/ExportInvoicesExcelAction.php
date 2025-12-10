<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Exports\InvoicesExport;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Maatwebsite\Excel\Excel as ExcelFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportInvoicesExcelAction
{
    public function __construct(
        private readonly ExcelFactory $excel,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $filters = [], ?User $user = null): StreamedResponse
    {
        $export = new InvoicesExport($filters, $user);
        $content = $this->excel->raw($export, ExcelFactory::XLSX);

        /** @var string $prefix */
        $prefix = config('invoicing.export.filename_prefix', 'invoices');

        $fileName = sprintf(
            '%s-%s.xlsx',
            $prefix,
            Date::now()->format('Ymd_His')
        );

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
