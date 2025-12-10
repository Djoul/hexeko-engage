<?php

declare(strict_types=1);

namespace App\Console\Commands\Translations;

use App\Actions\Translation\ReconcileTranslationsAction;
use App\Services\EnvironmentService;
use Exception;
use Illuminate\Console\Command;

class ReconcileTranslationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:auto-reconcile
                            {--interface= : Specific interface to reconcile (mobile/web_financer/web_beneficiary)}
                            {--all : Reconcile all interfaces}
                            {--force : Force reconciliation even if recently run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile translation files from S3 to ensure consistency';

    private const VALID_INTERFACES = ['mobile', 'web_financer', 'web_beneficiary'];

    public function __construct(
        private readonly ReconcileTranslationsAction $action,
        private readonly EnvironmentService $environmentService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->environmentService->useEnvironment();

        // Check environment restrictions
        if (! $this->environmentService->shouldReconcile()) {
            $this->info('Reconciliation is not enabled in '.app()->environment().' environment.');

            return self::SUCCESS;
        }

        if ($this->environmentService->shouldWarnOnMigrationWithoutReconciliation()) {
            $this->warn('⚠️  Reconciliation is disabled in dev environment. Consider enabling before running migrations.');
        }

        $interface = $this->option('interface');
        $allInterfaces = (bool) $this->option('all');
        $force = $this->option('force');

        // Validate interface if provided
        if ($interface && ! in_array($interface, self::VALID_INTERFACES)) {
            $this->error('Invalid interface: '.$interface.'. Must be one of: '.implode(', ', self::VALID_INTERFACES));

            return self::SUCCESS;
        }

        $this->info('Starting translation reconciliation...');

        try {
            $interfaces = match (true) {
                $allInterfaces => self::VALID_INTERFACES,
                $interface !== null => [$interface],
                default => self::VALID_INTERFACES,
            };

            $options = [
                'interfaces' => $interfaces,
                'force' => (bool) $force,
            ];

            $result = $this->action->execute($options);

            if ($result->totalFilesSynced === 0 && $result->totalJobsDispatched === 0) {
                $this->info('No changes detected during reconciliation.');

                return self::SUCCESS;
            }

            // Display results in a table
            $tableData = [];
            foreach ($result->interfaces as $interface => $data) {
                $tableData[] = [
                    $interface,
                    $data['files_synced'],
                    $data['jobs_dispatched'],
                ];
            }

            $this->table(
                ['Interface', 'Files Synced', 'Jobs Dispatched'],
                $tableData
            );

            $this->info('✓ Reconciliation completed successfully');
            $this->info('Run ID: '.$result->runId);
            $this->info('Total files synced: '.$result->totalFilesSynced);
            $this->info('Total jobs dispatched: '.$result->totalJobsDispatched);

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error('✗ Reconciliation failed: '.$e->getMessage());

            return self::SUCCESS;
        }
    }
}
