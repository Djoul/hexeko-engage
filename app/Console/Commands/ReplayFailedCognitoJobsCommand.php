<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Cognito\SendAuthEmailJob;
use App\Jobs\Cognito\SendSmsJob;
use App\Models\CognitoAuditLog;
use Exception;
use Illuminate\Console\Command;

class ReplayFailedCognitoJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cognito:replay-failed
                            {--type=all : Type of jobs to replay (sms|email|all)}
                            {--hours=24 : Only replay jobs failed in the last N hours}
                            {--limit=100 : Maximum number of jobs to replay}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Replay failed Cognito notification jobs from audit logs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->option('type');
        if (! is_string($type)) {
            $this->error('Invalid type option');

            return 1;
        }

        $hours = (int) $this->option('hours');
        $limit = (int) $this->option('limit');

        $this->info('Replaying failed Cognito jobs...');
        $this->newLine();

        // Query failed audit logs
        $query = CognitoAuditLog::where('status', 'failed')
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'desc');

        // Filter by type if not 'all'
        if ($type !== 'all') {
            if (! in_array($type, ['sms', 'email'])) {
                $this->error("Invalid type: {$type}. Must be 'sms', 'email', or 'all'");

                return 1;
            }
            $query->where('type', $type);
        }

        $failedLogs = $query->limit($limit)->get();

        if ($failedLogs->isEmpty()) {
            $this->warn('No failed jobs found to replay.');

            return 0;
        }

        $this->comment("Found {$failedLogs->count()} failed job(s) to replay.");
        $this->newLine();

        // Display summary table
        $this->table(
            ['ID', 'Type', 'Trigger', 'Locale', 'Failed At', 'Error'],
            $failedLogs->map(function ($log): array {
                return [
                    $log->id,
                    $log->type,
                    substr($log->trigger_source, 0, 25),
                    $log->locale,
                    $log->created_at->diffForHumans(),
                    substr($log->error_message ?? 'N/A', 0, 40).'...',
                ];
            })->toArray()
        );

        $this->newLine();

        if (! $this->confirm('Do you want to replay these jobs?', true)) {
            $this->info('Replay cancelled.');

            return 0;
        }

        // Replay jobs with progress bar
        $bar = $this->output->createProgressBar($failedLogs->count());
        $bar->start();

        $successCount = 0;
        $errorCount = 0;

        foreach ($failedLogs as $log) {
            try {
                $this->replayJob($log);
                $successCount++;
            } catch (Exception $e) {
                $errorCount++;
                $this->newLine();
                $this->error("Failed to replay job {$log->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Display results
        $this->info('✅ Replay completed!');
        $this->table(
            ['Status', 'Count'],
            [
                ['Successfully replayed', $successCount],
                ['Failed to replay', $errorCount],
                ['Total', $failedLogs->count()],
            ]
        );

        return $errorCount > 0 ? 1 : 0;
    }

    private function replayJob(CognitoAuditLog $log): void
    {
        // Decrypt payload
        $payload = $log->getDecryptedPayload();

        // Dispatch appropriate job based on type
        if ($log->type === 'sms') {
            // Extract identifier (priority: identifier > email > phone_number)
            $identifier = '';
            if (array_key_exists('identifier', $payload) && is_string($payload['identifier'])) {
                $identifier = $payload['identifier'];
            } elseif (array_key_exists('email', $payload) && is_string($payload['email'])) {
                $identifier = $payload['email'];
            } elseif (array_key_exists('phone_number', $payload) && is_string($payload['phone_number'])) {
                $identifier = $payload['phone_number'];
            }

            $sub = array_key_exists('sub', $payload) && is_string($payload['sub']) ? $payload['sub'] : null;
            $code = array_key_exists('code', $payload) && is_string($payload['code']) ? $payload['code'] : '';

            SendSmsJob::dispatch(
                identifier: $identifier,
                sub: $sub,
                code: $code,
                triggerSource: $log->trigger_source,
                locale: $log->locale,
                auditLogId: $log->id
            );
        } elseif ($log->type === 'email') {
            $email = array_key_exists('email', $payload) && is_string($payload['email']) ? $payload['email'] : '';
            $sub = array_key_exists('sub', $payload) && is_string($payload['sub']) ? $payload['sub'] : null;
            $code = array_key_exists('code', $payload) && is_string($payload['code']) ? $payload['code'] : '';

            SendAuthEmailJob::dispatch(
                email: $email,
                sub: $sub,
                code: $code,
                triggerSource: $log->trigger_source,
                locale: $log->locale,
                auditLogId: $log->id
            );
        }

        // Update audit log status back to 'queued'
        $log->update([
            'status' => 'queued',
            'error_message' => null,
        ]);
    }
}
