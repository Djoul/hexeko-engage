<?php

declare(strict_types=1);

namespace App\Console\Commands\Translations;

use App\Actions\Translation\RollbackTranslationAction;
use App\Services\EnvironmentService;
use Exception;
use Illuminate\Console\Command;

class RollbackTranslationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:rollback
                            {interface : The interface to rollback (mobile/web_financer/web_beneficiary)}
                            {--target-version= : Specific version to rollback to}
                            {--latest : Use the latest backup}
                            {--force : Force rollback without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback translation files to a previous version';

    public function __construct(
        private readonly RollbackTranslationAction $action,
        private readonly EnvironmentService $environmentService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $interface = $this->argument('interface');
        $version = $this->option('target-version');
        $useLatest = $this->option('latest');
        $force = $this->option('force');

        // Validate interface
        $validInterfaces = ['mobile', 'web_financer', 'web_beneficiary'];
        if (! in_array($interface, $validInterfaces, true)) {
            $this->error('Invalid interface. Must be one of: '.implode(', ', $validInterfaces));

            return self::FAILURE;
        }

        // Check environment permissions
        if (! $this->environmentService->canEditTranslations()) {
            $this->error('Translation rollback is not allowed in '.app()->environment().' environment.');
            $this->info('Rollback is only allowed in staging environment.');

            return self::FAILURE;
        }

        // Determine version
        if (! $version && ! $useLatest) {
            $this->error('You must specify either --target-version or --latest');

            return self::FAILURE;
        }

        // Confirmation
        if (! $force) {
            $confirmMessage = $useLatest
                ? "Are you sure you want to rollback {$interface} to the latest backup?"
                : "Are you sure you want to rollback {$interface} to version {$version}?";

            if (! $this->confirm($confirmMessage)) {
                $this->info('Rollback cancelled.');

                return self::SUCCESS;
            }
        }

        $this->info("Starting rollback for {$interface}...");

        try {
            $options = [
                'interface' => $interface,
                'version' => $version,
                'useLatest' => $useLatest,
            ];

            $result = $this->action->execute($options);

            if (! $result->success) {
                $this->error('✗ Rollback failed: '.$result->error);

                return self::FAILURE;
            }

            $this->info('✓ Rollback completed successfully');
            $this->info('Interface: '.$result->interface);
            $this->info('Rolled back to version: '.$result->restoredVersion);
            $this->info('Previous version backed up as: '.$result->backupPath);
            $this->info('Files affected: '.$result->filesAffected);

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error('✗ Rollback failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
