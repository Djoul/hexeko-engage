<?php

namespace App\Subscribers;

use Illuminate\Events\Dispatcher;
use Illuminate\Log\Logger;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogJobSubscriber
{
    /**
     * Store job start times and request IDs.
     *
     * @var array<string, array{start_time: float, request_id: string}>
     */
    private array $jobStartTimes = [];

    /**
     * Subscribe to job events.
     *
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            JobProcessing::class => 'onJobProcessing',
            JobProcessed::class => 'onJobProcessed',
            JobFailed::class => 'onJobFailed',
            JobExceptionOccurred::class => 'onJobException',
            // Note: JobBatchFinished event is not available in this Laravel version
        ];
    }

    /**
     * Handle job processing event.
     */
    public function onJobProcessing(JobProcessing $event): void
    {
        if (! $this->shouldLogJob($event->job->getName())) {
            return;
        }

        $jobId = $event->job->getJobId();
        $requestId = $this->generateRequestId();

        // Store start time and request ID
        $this->jobStartTimes[$jobId] = [
            'start_time' => microtime(true),
            'request_id' => $requestId,
        ];

        /** @var string $channelName */
        $channelName = config('logging.job_logging.channel', 'stack');
        /** @var Logger $logger */
        $logger = Log::channel($channelName);

        $logger->withContext([
            'request_id' => $requestId,
        ]);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($event->job->getRawBody(), true);
        $jobName = $event->job->resolveName();
        $logTitle = sprintf('[JOB] %s (processing, queue: %s, attempt: %d)', $jobName, $event->job->getQueue(), $event->job->attempts());

        $logger->info('job :'.$logTitle, [
            'event' => 'job.processing',
            'job' => [
                'name' => $event->job->getName(),
                'resolved_name' => $jobName,
                'id' => $jobId,
                'attempts' => $event->job->attempts(),
                'queue' => $event->job->getQueue(),
            ],
            'connection' => $event->connectionName,
            'payload' => config('logging.job_logging.log_payload', false)
                ? $this->sanitizePayload($payload)
                : null,
            'request_id' => $requestId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Handle job processed event.
     */
    public function onJobProcessed(JobProcessed $event): void
    {
        if (! $this->shouldLogJob($event->job->getName())) {
            return;
        }

        $jobId = $event->job->getJobId();

        if (! array_key_exists($jobId, $this->jobStartTimes)) {
            return;
        }

        $executionTime = round((microtime(true) - $this->jobStartTimes[$jobId]['start_time']) * 1000, 2);
        $logLevel = $this->getLogLevelForPerformance($executionTime);

        /** @var string $channelName */
        $channelName = config('logging.job_logging.channel', 'stack');
        /** @var Logger $logger */
        $logger = Log::channel($channelName);

        $logger->withContext([
            'request_id' => $this->jobStartTimes[$jobId]['request_id'],
        ]);

        $jobName = $event->job->resolveName();
        $logTitle = sprintf('[JOB] %s (processed, %sms)', $jobName, number_format($executionTime, 2));

        $logger->$logLevel('job :'.$logTitle, [
            'event' => 'job.processed',
            'job' => [
                'name' => $event->job->getName(),
                'id' => $jobId,
            ],
            'performance' => [
                'execution_time_ms' => $executionTime,
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'is_slow' => $executionTime > config('logging.job_logging.slow_threshold_ms', 5000),
            ],
            'request_id' => $this->jobStartTimes[$jobId]['request_id'],
            'timestamp' => now()->toIso8601String(),
        ]);

        // Clean up memory
        unset($this->jobStartTimes[$jobId]);
    }

    /**
     * Handle job failed event.
     */
    public function onJobFailed(JobFailed $event): void
    {
        $jobId = $event->job->getJobId();

        /** @var string $channelName */
        $channelName = config('logging.job_logging.channel', 'stack');
        /** @var Logger $logger */
        $logger = Log::channel($channelName);

        if (array_key_exists($jobId, $this->jobStartTimes)) {
            $logger->withContext([
                'request_id' => $this->jobStartTimes[$jobId]['request_id'],
            ]);

            $executionTime = round((microtime(true) - $this->jobStartTimes[$jobId]['start_time']) * 1000, 2);
        } else {
            $executionTime = null;
        }

        $jobName = $event->job->resolveName();
        $logTitle = sprintf('[JOB] %s (failed: %s, attempt: %d)', $jobName, $event->exception->getMessage(), $event->job->attempts());

        $logger->error('job :'.$logTitle, [
            'event' => 'job.failed',
            'job' => [
                'name' => $event->job->getName(),
                'id' => $jobId,
                'attempts' => $event->job->attempts(),
                'queue' => $event->job->getQueue(),
            ],
            'connection' => $event->connectionName,
            'exception' => [
                'class' => get_class($event->exception),
                'message' => $event->exception->getMessage(),
                'file' => $event->exception->getFile(),
                'line' => $event->exception->getLine(),
                'trace' => config('app.debug') ? $event->exception->getTraceAsString() : null,
            ],
            'performance' => $executionTime ? [
                'execution_time_ms' => $executionTime,
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ] : null,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Clean up memory
        if (array_key_exists($jobId, $this->jobStartTimes)) {
            unset($this->jobStartTimes[$jobId]);
        }
    }

    /**
     * Handle job exception occurred event.
     */
    public function onJobException(JobExceptionOccurred $event): void
    {
        if (! $this->shouldLogJob($event->job->getName())) {
            return;
        }

        $jobId = $event->job->getJobId();

        /** @var string $channelName */
        $channelName = config('logging.job_logging.channel', 'stack');
        /** @var Logger $logger */
        $logger = Log::channel($channelName);

        if (array_key_exists($jobId, $this->jobStartTimes)) {
            $logger->withContext([
                'request_id' => $this->jobStartTimes[$jobId]['request_id'],
            ]);
        }

        $jobName = $event->job->resolveName();
        $logTitle = sprintf('[JOB] %s (exception: %s, attempt: %d)', $jobName, $event->exception->getMessage(), $event->job->attempts());

        $logger->warning('job :'.$logTitle, [
            'event' => 'job.exception',
            'job' => [
                'name' => $event->job->getName(),
                'id' => $jobId,
                'attempts' => $event->job->attempts(),
            ],
            'exception' => [
                'class' => get_class($event->exception),
                'message' => $event->exception->getMessage(),
                'file' => $event->exception->getFile(),
                'line' => $event->exception->getLine(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Sanitize sensitive data from job payload.
     *
     * @param  array<mixed, mixed>|null  $payload
     * @return array<mixed, mixed>|null
     */
    private function sanitizePayload(?array $payload): ?array
    {
        if ($payload === null || $payload === []) {
            return null;
        }

        $sensitive = [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'api_key',
            'api_secret',
            'access_token',
            'refresh_token',
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
        ];

        return collect($payload)->map(function ($value, $key) use ($sensitive) {
            if (is_string($key)) {
                foreach ($sensitive as $sensitiveWord) {
                    if (Str::contains(strtolower($key), $sensitiveWord)) {
                        return '***REDACTED***';
                    }
                }
            }

            if (is_array($value)) {
                return $this->sanitizePayload($value);
            }

            return $value;
        })->toArray();
    }

    /**
     * Determine if the job should be logged.
     */
    private function shouldLogJob(string $jobName): bool
    {
        if (! config('logging.job_logging.enabled', true)) {
            return false;
        }

        /** @var array<int, string> $excludedJobs */
        $excludedJobs = config('logging.job_logging.excluded_jobs', []);

        return ! in_array($jobName, $excludedJobs, true);
    }

    /**
     * Get log level based on job execution time.
     */
    private function getLogLevelForPerformance(float $executionTimeMs): string
    {
        $criticalThreshold = config('logging.job_logging.critical_threshold_ms', 30000);
        $warningThreshold = config('logging.job_logging.warning_threshold_ms', 10000);

        return match (true) {
            $executionTimeMs > $criticalThreshold => 'error',
            $executionTimeMs > $warningThreshold => 'warning',
            default => 'info',
        };
    }

    private function generateRequestId(): string
    {
        $uuid = (string) Str::uuid();

        return str_replace('-', '', $uuid);
    }
}
