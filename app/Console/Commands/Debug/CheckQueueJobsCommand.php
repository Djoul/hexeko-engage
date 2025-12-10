<?php

namespace App\Console\Commands\Debug;

use App\Actions\Apideck\SyncAllEmployeesAction;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class CheckQueueJobsCommand extends Command
{
    protected $signature = 'queue:check-jobs
                            {--job= : Filter by job class name}
                            {--status=all : Filter by status (all, pending, failed, completed)}
                            {--limit=10 : Number of jobs to display}';

    protected $description = 'Check queued jobs and their status';

    public function handle(): int
    {
        $jobFilter = $this->option('job');
        $status = $this->option('status');
        $limit = (int) $this->option('limit');

        // Check jobs table
        $this->info('📋 Checking Jobs Queue...');
        $this->line('');

        $query = DB::table('jobs')
            ->select('id', 'queue', 'payload', 'attempts', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        $jobs = $query->get();

        if ($jobs->isEmpty()) {
            $this->info('No pending jobs in queue.');
        } else {
            $this->info('Pending Jobs:');
            $rows = [];
            foreach ($jobs as $job) {
                $payload = json_decode($job->payload, true);
                $jobClass = (is_array($payload) && array_key_exists('displayName', $payload) && is_string($payload['displayName'])) ? $payload['displayName'] : 'Unknown';

                if ($jobFilter && ! str_contains($jobClass, $jobFilter)) {
                    continue;
                }

                $rows[] = [
                    $job->id,
                    $jobClass,
                    $job->queue,
                    $job->attempts,
                    $job->created_at,
                ];
            }

            if ($rows !== []) {
                $this->table(
                    ['ID', 'Job Class', 'Queue', 'Attempts', 'Created At'],
                    $rows
                );
            }
        }

        // Check failed jobs
        if ($status === 'all' || $status === 'failed') {
            $this->line('');
            $this->info('❌ Failed Jobs:');

            $failedJobs = DB::table('failed_jobs')
                ->select('id', 'queue', 'payload', 'exception', 'failed_at')
                ->orderBy('failed_at', 'desc')
                ->limit($limit)
                ->get();

            if ($failedJobs->isEmpty()) {
                $this->info('No failed jobs.');
            } else {
                $rows = [];
                foreach ($failedJobs as $job) {
                    $payload = json_decode($job->payload, true);
                    $jobClass = (is_array($payload) && array_key_exists('displayName', $payload) && is_string($payload['displayName'])) ? $payload['displayName'] : 'Unknown';

                    if ($jobFilter && ! str_contains($jobClass, $jobFilter)) {
                        continue;
                    }

                    $exception = substr($job->exception, 0, 50).'...';
                    $rows[] = [
                        $job->id,
                        $jobClass,
                        $job->queue,
                        $exception,
                        $job->failed_at,
                    ];
                }

                if ($rows !== []) {
                    $this->table(
                        ['ID', 'Job Class', 'Queue', 'Exception', 'Failed At'],
                        $rows
                    );
                }
            }
        }

        // Check for SyncAllEmployeesAction specifically
        if (! $jobFilter || str_contains('SyncAllEmployeesAction', $jobFilter)) {
            $this->line('');
            $this->info('🔍 SyncAllEmployeesAction Status:');

            // Check if it's configured to run in queue
            $actionClass = SyncAllEmployeesAction::class;
            $implementsQueue = in_array(
                ShouldQueue::class,
                class_implements($actionClass)
            );

            if ($implementsQueue) {
                $this->info('✅ SyncAllEmployeesAction implements ShouldQueue - Will run in queue');
            } else {
                $this->error('❌ SyncAllEmployeesAction does NOT implement ShouldQueue - Will run synchronously');
            }

            // Count recent sync jobs
            $recentSyncJobs = DB::table('jobs')
                ->where('payload', 'like', '%SyncAllEmployeesAction%')
                ->count();

            $failedSyncJobs = DB::table('failed_jobs')
                ->where('payload', 'like', '%SyncAllEmployeesAction%')
                ->count();

            $this->line("Pending sync jobs: {$recentSyncJobs}");
            $this->line("Failed sync jobs: {$failedSyncJobs}");
        }

        return self::SUCCESS;
    }
}
