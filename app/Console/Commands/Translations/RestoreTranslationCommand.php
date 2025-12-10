<?php

declare(strict_types=1);

namespace App\Console\Commands\Translations;

use App\Actions\Translation\RestoreTranslationAction;
use App\Services\EnvironmentService;
use Exception;
use Illuminate\Console\Command;

class RestoreTranslationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:restore
                            {interface : The interface to restore (mobile/web_financer/web_beneficiary)}
                            {--latest : Restore from latest backup}
                            {--backup= : Specific backup file to restore from}
                            {--list : List available backups}
                            {--force : Force restore without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore translation files from S3 backups';

    public function __construct(
        private readonly RestoreTranslationAction $action,
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
        $listBackups = $this->option('list');
        $useLatest = $this->option('latest');
        $backupFile = $this->option('backup');
        $force = $this->option('force');

        // Validate interface
        $validInterfaces = ['mobile', 'web_financer', 'web_beneficiary'];
        if (! in_array($interface, $validInterfaces, true)) {
            $this->error('Invalid interface. Must be one of: '.implode(', ', $validInterfaces));

            return self::FAILURE;
        }

        // List backups if requested
        if ($listBackups) {
            return $this->listAvailableBackups($interface);
        }

        // Check environment permissions for restore
        if (! $this->environmentService->canEditTranslations() && ! app()->environment('local')) {
            $this->error('Translation restore is not allowed in '.app()->environment().' environment.');
            $this->info('Restore is allowed in local and staging environments only.');

            return self::FAILURE;
        }

        // Validate restore options
        if (! $useLatest && ! $backupFile) {
            $this->error('You must specify either --latest or --backup=<filename>');
            $this->info('Use --list to see available backups');

            return self::FAILURE;
        }

        // Confirmation
        if (! $force) {
            $source = $useLatest ? 'latest backup' : "backup file: {$backupFile}";
            if (! $this->confirm("Are you sure you want to restore {$interface} from {$source}?")) {
                $this->info('Restore cancelled.');

                return self::SUCCESS;
            }
        }

        $this->info("Starting restore for {$interface}...");

        try {
            $options = [
                'interface' => $interface,
                'useLatest' => $useLatest,
                'backupFile' => $backupFile,
            ];

            $result = $this->action->execute($options);

            if (! $result->success) {
                $this->error('✗ Restore failed: '.$result->error);

                return self::FAILURE;
            }

            $this->info('✓ Restore completed successfully');
            $this->info('Interface: '.$result->interface);
            $this->info('Restored from: '.$result->backupFile);
            $this->info('Version restored: '.$result->restoredVersion);
            $this->info('Keys restored: '.$result->keysRestored);
            $this->table(
                ['Language', 'Keys'],
                collect($result->languageBreakdown)->map(fn ($count, $lang): array => [$lang, $count])->toArray()
            );

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error('✗ Restore failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * List available backups for an interface.
     */
    private function listAvailableBackups(string $interface): int
    {
        $this->info("Available backups for {$interface}:");

        try {
            $backups = $this->action->listBackups($interface);

            if ($backups->isEmpty()) {
                $this->warn('No backups found.');

                return self::SUCCESS;
            }

            $tableData = $backups->map(function ($backup): array {
                return [
                    $backup->filename,
                    $backup->version,
                    $backup->createdAt->format('Y-m-d H:i:s'),
                    $this->formatFileSize($backup->size),
                ];
            })->toArray();

            $this->table(
                ['Filename', 'Version', 'Created', 'Size'],
                $tableData
            );

            $this->info('Total backups: '.$backups->count());

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error('Failed to list backups: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Format file size for display.
     */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $kb = $bytes / 1024;
        if ($kb < 1024) {
            return round($kb, 2).' KB';
        }

        $mb = $kb / 1024;

        return round($mb, 2).' MB';
    }
}
