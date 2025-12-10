<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Exports\UserBillingDetailsExport;
use Illuminate\Support\Facades\Date;
use Maatwebsite\Excel\Excel as ExcelFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportUserBillingExcelAction
{
    public function __construct(
        private readonly ExcelFactory $excel,
    ) {}

    public function execute(string $invoiceId): StreamedResponse
    {
        $export = new UserBillingDetailsExport($invoiceId);
        $content = $this->excel->raw($export, ExcelFactory::XLSX);

        $fileName = sprintf(
            'user-billing-details-%s-%s.xlsx',
            $invoiceId,
            Date::now()->format('Ymd_His')
        );

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
