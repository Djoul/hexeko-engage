<?php

declare(strict_types=1);

namespace App\Console\Commands\Invoicing;

use App\Actions\Invoicing\GenerateMonthlyInvoicesAction;
use App\DTOs\Invoicing\InvoiceBatchDTO;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

class GenerateMonthlyInvoicesCommand extends Command
{
    protected $signature = 'financer:generate-invoices {--month=} {--division=} {--financer=} {--dry-run}';

    protected $description = 'Generate monthly invoices for divisions and financers.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $monthYear = $this->resolveMonthOption();
        $divisionId = $this->option('division') ?: null;
        $financerId = $this->option('financer') ?: null;
        $dryRun = (bool) $this->option('dry-run');

        $action = app(GenerateMonthlyInvoicesAction::class);

        $batch = $action->execute($monthYear, $divisionId, $financerId, $dryRun);

        $this->outputSummary($batch);

        return self::SUCCESS;
    }

    private function resolveMonthOption(): string
    {
        $month = $this->option('month');

        if (is_string($month) && $month !== '') {
            return $month;
        }

        return Date::now()->format('Y-m');
    }

    private function outputSummary(InvoiceBatchDTO $batch): void
    {
        $this->line('Invoice generation summary');
        $this->line('Batch ID: '.$batch->batchId);
        $this->line('Month: '.$batch->monthYear);
        $this->line('Status: '.$batch->status);
        $this->line('Total invoices: '.$batch->totalInvoices);
    }
}
