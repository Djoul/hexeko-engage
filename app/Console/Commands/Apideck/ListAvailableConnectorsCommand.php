<?php

namespace App\Console\Commands\Apideck;

use Illuminate\Console\Command;

class ListAvailableConnectorsCommand extends Command
{
    protected $signature = 'apideck:connectors';

    protected $description = 'List available SIRH connectors that can be used with Apideck';

    public function handle(): int
    {
        $this->info('Available Apideck SIRH Connectors:');
        $this->line('');

        $connectors = [
            'bamboohr' => 'BambooHR - Popular HR platform for small and medium businesses',
            'personio' => 'Personio - All-in-one HR software for small and medium-sized companies',
            'workday' => 'Workday - Enterprise-grade HR and finance platform',
            'hibob' => 'HiBob - Modern HR platform for dynamic companies',
            'namely' => 'Namely - HR platform focused on mid-sized companies',
            'sage-hr' => 'Sage HR - HR and people management software',
            'adp-workforce-now' => 'ADP Workforce Now - Comprehensive HR management solution',
        ];

        foreach ($connectors as $id => $description) {
            $this->line("  <fg=cyan>{$id}</fg=cyan>");
            $this->line("    {$description}");
            $this->line('');
        }

        $currentConnector = config('services.apideck.service_id', 'bamboohr');
        $connectorString = is_string($currentConnector) ? $currentConnector : 'bamboohr';
        $this->info('Currently configured: '.$connectorString);
        $this->line('');
        $this->comment('To change the connector, set APIDECK_SERVICE_ID in your .env file');
        $this->comment('Example: APIDECK_SERVICE_ID=workday');

        return Command::SUCCESS;
    }
}
