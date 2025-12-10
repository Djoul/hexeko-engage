<?php

namespace App\Console\Commands;

use App\Services\DemoPurgeService;
use Exception;
use Illuminate\Console\Command;

class DemoPurgeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:purge 
                            {--soft : Perform soft deletion instead of hard deletion}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge all demo data from the database';

    /**
     * Execute the console command.
     */
    public function handle(DemoPurgeService $purgeService): int
    {
        // Production guard
        if (app()->environment('production') && ! config('demo.allowed')) {
            $this->error('Demo purge is not allowed in production!');

            return self::FAILURE;
        }

        $isDryRun = $this->option('dry-run');
        $isSoftDelete = $this->option('soft');
        $force = $this->option('force');

        // Show current demo data statistics
        $this->info('Analyzing demo data...');
        $candidates = $purgeService->getSoftDeleteCandidates();

        $this->table(
            ['Type', 'Count'],
            [
                ['Articles', collect($candidates)->where('type', 'Article')->count()],
                ['Links', collect($candidates)->where('type', 'Link')->count()],
                ['Users', collect($candidates)->where('type', 'User')->count()],
                ['Financers', collect($candidates)->where('type', 'Financer')->count()],
                ['Divisions', collect($candidates)->where('type', 'Division')->count()],
            ]
        );

        // Confirmation prompt
        if (! $force && ! $isDryRun) {
            $action = $isSoftDelete ? 'soft delete' : 'permanently delete';
            if (! $this->confirm("Are you sure you want to {$action} all demo data?")) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        // Execute purge
        if ($isDryRun) {
            $this->info('Running in DRY RUN mode - no data will be deleted');
        }

        $this->info($isSoftDelete ? 'Soft deleting demo data...' : 'Purging demo data...');

        try {
            if ($isSoftDelete && ! $isDryRun) {
                $statistics = $purgeService->softDelete();
                $this->displaySoftDeleteResults($statistics);
            } else {
                $statistics = $purgeService->purge($isDryRun);
                $this->displayPurgeResults($statistics, $isDryRun);
            }

            $this->info($isDryRun ? 'Dry run completed successfully!' : 'Demo data purged successfully!');

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error('Failed to purge demo data: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Display purge results
     *
     * @param  array<string, mixed>  $statistics
     */
    protected function displayPurgeResults(array $statistics, bool $isDryRun): void
    {
        $action = $isDryRun ? 'Would delete' : 'Deleted';

        $this->table(
            ['Entity', $action],
            [
                ['Articles', $statistics['articles_deleted'] ?? 0],
                ['Links', $statistics['links_deleted'] ?? 0],
                ['Users', $statistics['users_deleted']],
                ['Financers', $statistics['financers_deleted']],
                ['Divisions', $statistics['divisions_deleted']],
                ['Demo Entity Records', $statistics['demo_entities_deleted']],
            ]
        );
    }

    /**
     * Display soft delete results
     *
     * @param  array<string, mixed>  $statistics
     */
    protected function displaySoftDeleteResults(array $statistics): void
    {
        $this->table(
            ['Entity', 'Soft Deleted'],
            [
                ['Articles', $statistics['articles_soft_deleted'] ?? 0],
                ['Links', $statistics['links_soft_deleted'] ?? 0],
                ['Users', $statistics['users_soft_deleted']],
                ['Financers', $statistics['financers_soft_deleted']],
                ['Divisions', $statistics['divisions_soft_deleted']],
            ]
        );
    }
}
