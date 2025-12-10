<?php

namespace Tests\Unit\Subscribers;

use App\Subscribers\LogJobSubscriber;
use Exception;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[Group('logging')]
class LogJobSubscriberTest extends TestCase
{
    private LogJobSubscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriber = new LogJobSubscriber;
    }

    #[Test]
    public function it_subscribes_to_job_events(): void
    {
        $dispatcher = $this->app->make('events');

        $subscriptions = $this->subscriber->subscribe($dispatcher);

        $this->assertEquals([
            JobProcessing::class => 'onJobProcessing',
            JobProcessed::class => 'onJobProcessed',
            JobFailed::class => 'onJobFailed',
            JobExceptionOccurred::class => 'onJobException',
        ], $subscriptions);
    }

    #[Test]
    public function it_logs_job_processing_event(): void
    {
        Config::set('logging.job_logging.enabled', true);
        Config::set('logging.job_logging.log_payload', false);
        Config::set('logging.job_logging.channel', 'stack');

        /** @var Job|MockInterface $job */
        $job = $this->mock(Job::class);
        $job->shouldReceive('getName')->andReturn('App\Jobs\TestJob');
        $job->shouldReceive('resolveName')->andReturn('TestJob');
        $job->shouldReceive('getJobId')->andReturn('test-job-123');
        $job->shouldReceive('attempts')->andReturn(1);
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('getRawBody')->andReturn(json_encode([
            'displayName' => 'TestJob',
            'job' => 'Illuminate\Queue\CallQueuedHandler@call',
        ]));

        Log::shouldReceive('channel')
            ->with('stack')
            ->andReturnSelf();

        Log::shouldReceive('withContext')->once();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return str_starts_with($message, 'job :')
                    && $context['event'] === 'job.processing'
                    && $context['job']['name'] === 'App\Jobs\TestJob'
                    && $context['job']['id'] === 'test-job-123'
                    && isset($context['request_id']);
            });

        $event = new JobProcessing('redis', $job);
        $this->subscriber->onJobProcessing($event);
    }

    #[Test]
    public function it_logs_job_processing_with_payload(): void
    {
        Config::set('logging.job_logging.enabled', true);
        Config::set('logging.job_logging.log_payload', true);
        Config::set('logging.job_logging.channel', 'stack');

        /** @var Job|MockInterface $job */
        $job = $this->mock(Job::class);
        $job->shouldReceive('getName')->andReturn('App\Jobs\TestJob');
        $job->shouldReceive('resolveName')->andReturn('TestJob');
        $job->shouldReceive('getJobId')->andReturn('test-job-123');
        $job->shouldReceive('attempts')->andReturn(1);
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('getRawBody')->andReturn(json_encode([
            'displayName' => 'TestJob',
            'data' => ['user_id' => 1, 'email' => 'test@example.com'],
        ]));

        Log::shouldReceive('channel')->with('stack')->andReturnSelf();
        Log::shouldReceive('withContext')->once();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return str_starts_with($message, 'job :')
                    && isset($context['payload'])
                    && $context['payload']['data']['user_id'] === 1;
            });

        $event = new JobProcessing('redis', $job);
        $this->subscriber->onJobProcessing($event);
    }

    #[Test]
    public function it_logs_job_processed_event(): void
    {
        Config::set('logging.job_logging.enabled', true);
        Config::set('logging.job_logging.channel', 'stack');

        /** @var Job|MockInterface $job */
        $job = $this->mock(Job::class);
        $job->shouldReceive('getName')->andReturn('App\Jobs\TestJob');
        $job->shouldReceive('resolveName')->andReturn('TestJob');
        $job->shouldReceive('getJobId')->andReturn('test-job-123');
        $job->shouldReceive('attempts')->andReturn(1);
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('getRawBody')->andReturn(json_encode(['displayName' => 'TestJob']));

        // Start processing
        Log::shouldReceive('channel')->with('stack')->andReturnSelf();
        Log::shouldReceive('withContext')->once();
        Log::shouldReceive('info')->once();

        $processingEvent = new JobProcessing('redis', $job);
        $this->subscriber->onJobProcessing($processingEvent);

        // Finish processing
        Log::shouldReceive('channel')->with('stack')->andReturnSelf();
        Log::shouldReceive('withContext')->once();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return str_starts_with($message, 'job :')
                    && $context['event'] === 'job.processed'
                    && $context['job']['name'] === 'App\Jobs\TestJob'
                    && isset($context['performance']['execution_time_ms'])
                    && isset($context['performance']['memory_peak_mb']);
            });

        $processedEvent = new JobProcessed('redis', $job);
        $this->subscriber->onJobProcessed($processedEvent);
    }

    #[Test]
    public function it_logs_slow_job_with_warning(): void
    {
        Config::set('logging.job_logging.enabled', true);
        Config::set('logging.job_logging.channel', 'stack');
        Config::set('logging.job_logging.warning_threshold_ms', 100);

        /** @var Job|MockInterface $job */
        $job = $this->mock(Job::class);
        $job->shouldReceive('getName')->andReturn('App\Jobs\SlowJob');
        $job->shouldReceive('resolveName')->andReturn('SlowJob');
        $job->shouldReceive('getJobId')->andReturn('slow-job-123');
        $job->shouldReceive('attempts')->andReturn(1);
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('getRawBody')->andReturn(json_encode(['displayName' => 'SlowJob']));

        // Start processing
        Log::shouldReceive('channel')->with('stack')->andReturnSelf();
        Log::shouldReceive('withContext')->once();
        Log::shouldReceive('info')->once();

        $processingEvent = new JobProcessing('redis', $job);
        $this->subscriber->onJobProcessing($processingEvent);

        // Simulate slow execution
        usleep(150000); // 150ms

        // Finish processing
        Log::shouldReceive('channel')->with('stack')->andReturnSelf();
        Log::shouldReceive('withContext')->once();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return str_starts_with($message, 'job :')
                    && $context['event'] === 'job.processed'
                    && $context['performance']['execution_time_ms'] > 100;
            });

        $processedEvent = new JobProcessed('redis', $job);
        $this->subscriber->onJobProcessed($processedEvent);
    }

    #[Test]
    public function it_logs_job_failed_event(): void
    {
        Config::set('logging.job_logging.enabled', true);
        Config::set('logging.job_logging.channel', 'stack');

        /** @var Job|MockInterface $job */
        $job = $this->mock(Job::class);
        $job->shouldReceive('getName')->andReturn('App\Jobs\FailingJob');
        $job->shouldReceive('resolveName')->andReturn('FailingJob');
        $job->shouldReceive('getJobId')->andReturn('failing-job-123');
        $job->shouldReceive('attempts')->andReturn(2);
        $job->shouldReceive('getQueue')->andReturn('default');

        Log::shouldReceive('channel')->with('stack')->andReturnSelf();
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return str_starts_with($message, 'job :')
                    && $context['event'] === 'job.failed'
                    && $context['job']['name'] === 'App\Jobs\FailingJob'
                    && isset($context['exception']['class'])
                    && isset($context['exception']['message']);
            });

        $exception = new RuntimeException('Job failed unexpectedly');
        $event = new JobFailed('redis', $job, $exception);
        $this->subscriber->onJobFailed($event);
    }

    #[Test]
    public function it_logs_job_exception_occurred_event(): void
    {
        Config::set('logging.job_logging.enabled', true);
        Config::set('logging.job_logging.channel', 'stack');

        /** @var Job|MockInterface $job */
        $job = $this->mock(Job::class);
        $job->shouldReceive('getName')->andReturn('App\Jobs\TestJob');
        $job->shouldReceive('resolveName')->andReturn('TestJob');
        $job->shouldReceive('getJobId')->andReturn('test-job-123');
        $job->shouldReceive('attempts')->andReturn(1);
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('getRawBody')->andReturn(json_encode(['displayName' => 'TestJob']));

        // Start processing to set request ID
        Log::shouldReceive('channel')->with('stack')->andReturnSelf();
        Log::shouldReceive('withContext')->once();
        Log::shouldReceive('info')->once();

        $processingEvent = new JobProcessing('redis', $job);
        $this->subscriber->onJobProcessing($processingEvent);

        // Exception occurs
        Log::shouldReceive('channel')->with('stack')->andReturnSelf();
        Log::shouldReceive('withContext')->once();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return str_starts_with($message, 'job :')
                    && $context['event'] === 'job.exception'
                    && isset($context['exception']['class'])
                    && isset($context['exception']['message']);
            });

        $exception = new Exception('Something went wrong');
        $exceptionEvent = new JobExceptionOccurred('redis', $job, $exception);
        $this->subscriber->onJobException($exceptionEvent);
    }

    #[Test]
    public function it_sanitizes_sensitive_payload_data(): void
    {
        Config::set('logging.job_logging.enabled', true);
        Config::set('logging.job_logging.log_payload', true);
        Config::set('logging.job_logging.channel', 'stack');

        /** @var Job|MockInterface $job */
        $job = $this->mock(Job::class);
        $job->shouldReceive('getName')->andReturn('App\Jobs\TestJob');
        $job->shouldReceive('resolveName')->andReturn('TestJob');
        $job->shouldReceive('getJobId')->andReturn('test-job-123');
        $job->shouldReceive('attempts')->andReturn(1);
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('getRawBody')->andReturn(json_encode([
            'displayName' => 'TestJob',
            'data' => [
                'user_id' => 1,
                'password' => 'secret123',
                'api_key' => 'secret-key-123',
            ],
        ]));

        Log::shouldReceive('channel')->with('stack')->andReturnSelf();
        Log::shouldReceive('withContext')->once();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return isset($context['payload']['data'])
                    && $context['payload']['data']['password'] === '***REDACTED***'
                    && $context['payload']['data']['api_key'] === '***REDACTED***'
                    && $context['payload']['data']['user_id'] === 1;
            });

        $event = new JobProcessing('redis', $job);
        $this->subscriber->onJobProcessing($event);
    }

    #[Test]
    public function it_skips_logging_for_excluded_jobs(): void
    {
        Config::set('logging.job_logging.enabled', true);
        Config::set('logging.job_logging.excluded_jobs', ['App\Jobs\ExcludedJob']);

        /** @var Job|MockInterface $job */
        $job = $this->mock(Job::class);
        $job->shouldReceive('getName')->andReturn('App\Jobs\ExcludedJob');

        Log::shouldReceive('channel')->never();
        Log::shouldReceive('withContext')->never();
        Log::shouldReceive('info')->never();

        $event = new JobProcessing('redis', $job);
        $this->subscriber->onJobProcessing($event);
    }

    #[Test]
    public function it_skips_all_logging_when_disabled(): void
    {
        Config::set('logging.job_logging.enabled', false);

        /** @var Job|MockInterface $job */
        $job = $this->mock(Job::class);
        $job->shouldReceive('getName')->andReturn('App\Jobs\TestJob');

        Log::shouldReceive('channel')->never();
        Log::shouldReceive('withContext')->never();
        Log::shouldReceive('info')->never();

        $event = new JobProcessing('redis', $job);
        $this->subscriber->onJobProcessing($event);
    }

    #[Test]
    public function it_logs_failed_job_with_execution_time_when_available(): void
    {
        Config::set('logging.job_logging.enabled', true);
        Config::set('logging.job_logging.channel', 'stack');

        /** @var Job|MockInterface $job */
        $job = $this->mock(Job::class);
        $job->shouldReceive('getName')->andReturn('App\Jobs\TestJob');
        $job->shouldReceive('resolveName')->andReturn('TestJob');
        $job->shouldReceive('getJobId')->andReturn('test-job-123');
        $job->shouldReceive('attempts')->andReturn(1);
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('getRawBody')->andReturn(json_encode(['displayName' => 'TestJob']));

        // Start processing
        Log::shouldReceive('channel')->with('stack')->andReturnSelf();
        Log::shouldReceive('withContext')->once();
        Log::shouldReceive('info')->once();

        $processingEvent = new JobProcessing('redis', $job);
        $this->subscriber->onJobProcessing($processingEvent);

        // Fail with execution time
        Log::shouldReceive('channel')->with('stack')->andReturnSelf();
        Log::shouldReceive('withContext')->once();
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return str_starts_with($message, 'job :')
                    && $context['event'] === 'job.failed'
                    && isset($context['performance'])
                    && isset($context['performance']['execution_time_ms']);
            });

        $exception = new RuntimeException('Job failed');
        $failedEvent = new JobFailed('redis', $job, $exception);
        $this->subscriber->onJobFailed($failedEvent);
    }

    #[Test]
    public function it_does_not_include_payload_when_disabled(): void
    {
        Config::set('logging.job_logging.enabled', true);
        Config::set('logging.job_logging.log_payload', false);
        Config::set('logging.job_logging.channel', 'stack');

        /** @var Job|MockInterface $job */
        $job = $this->mock(Job::class);
        $job->shouldReceive('getName')->andReturn('App\Jobs\TestJob');
        $job->shouldReceive('resolveName')->andReturn('TestJob');
        $job->shouldReceive('getJobId')->andReturn('test-job-123');
        $job->shouldReceive('attempts')->andReturn(1);
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('getRawBody')->andReturn(json_encode(['displayName' => 'TestJob']));

        Log::shouldReceive('channel')->with('stack')->andReturnSelf();
        Log::shouldReceive('withContext')->once();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return $context['payload'] === null;
            });

        $event = new JobProcessing('redis', $job);
        $this->subscriber->onJobProcessing($event);
    }
}
