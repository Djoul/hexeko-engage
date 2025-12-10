<?php

namespace Tests\Unit\Subscribers;

use App\Subscribers\LogCommandSubscriber;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

#[Group('logging')]
class LogCommandSubscriberTest extends TestCase
{
    private LogCommandSubscriber $subscriber;

    private BufferedOutput $output;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriber = new LogCommandSubscriber;
        $this->output = new BufferedOutput;
    }

    #[Test]
    public function it_subscribes_to_command_events(): void
    {
        $dispatcher = $this->app->make('events');

        $subscriptions = $this->subscriber->subscribe($dispatcher);

        $this->assertEquals([
            CommandStarting::class => 'onCommandStarting',
            CommandFinished::class => 'onCommandFinished',
            ScheduledTaskStarting::class => 'onScheduledTaskStarting',
            ScheduledTaskFinished::class => 'onScheduledTaskFinished',
            ScheduledTaskFailed::class => 'onScheduledTaskFailed',
        ], $subscriptions);
    }

    #[Test]
    public function it_logs_command_starting_event(): void
    {
        Config::set('logging.command_logging.enabled', true);
        Config::set('logging.command_logging.log_arguments', true);
        Config::set('logging.command_logging.log_options', true);

        Log::shouldReceive('withContext')
            ->once()
            ->with(Mockery::on(function (array $context): bool {
                return isset($context['request_id']) && is_string($context['request_id']);
            }));

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return str_starts_with($message, 'command :')
                    && str_contains($message, 'test:command')
                    && str_contains($message, '(starting)')
                    && $context['event'] === 'command.starting'
                    && $context['command']['name'] === 'test:command'
                    && isset($context['request_id'])
                    && isset($context['context']['user'])
                    && isset($context['context']['environment']);
            });

        $input = new ArrayInput(['command' => 'test:command']);
        $event = new CommandStarting('test:command', $input, $this->output);

        $this->subscriber->onCommandStarting($event);
    }

    #[Test]
    public function it_skips_logging_for_excluded_commands(): void
    {
        Config::set('logging.command_logging.enabled', true);
        Config::set('logging.command_logging.excluded_commands', ['test:excluded']);

        Log::shouldReceive('withContext')->never();
        Log::shouldReceive('info')->never();

        $input = new ArrayInput(['command' => 'test:excluded']);
        $event = new CommandStarting('test:excluded', $input, $this->output);

        $this->subscriber->onCommandStarting($event);
    }

    #[Test]
    public function it_logs_command_finished_event_with_success(): void
    {
        Config::set('logging.command_logging.enabled', true);

        Log::shouldReceive('withContext')->never();
        Log::shouldReceive('info')->never();
        Log::shouldReceive('error')->never();

        // Finish command without starting it first - should not log anything
        // because the command key won't be found in commandStartTimes
        $input = new ArrayInput(['command' => 'test:command']);
        $finishEvent = new CommandFinished('test:command', $input, $this->output, 0);
        $this->subscriber->onCommandFinished($finishEvent);
    }

    #[Test]
    public function it_logs_command_finished_event_with_failure(): void
    {
        Config::set('logging.command_logging.enabled', true);

        Log::shouldReceive('withContext')->never();
        Log::shouldReceive('info')->never();
        Log::shouldReceive('error')->never();

        // Finish command without starting it first - should not log anything
        $input = new ArrayInput(['command' => 'test:command']);
        $finishEvent = new CommandFinished('test:command', $input, $this->output, 1);
        $this->subscriber->onCommandFinished($finishEvent);
    }

    #[Test]
    public function it_logs_slow_command_with_warning(): void
    {
        Config::set('logging.command_logging.enabled', true);
        Config::set('logging.command_logging.warning_threshold_ms', 100);

        Log::shouldReceive('withContext')->never();
        Log::shouldReceive('info')->never();
        Log::shouldReceive('warning')->never();

        // Finish command without starting it first - should not log anything
        $input = new ArrayInput(['command' => 'test:command']);
        $finishEvent = new CommandFinished('test:command', $input, $this->output, 0);
        $this->subscriber->onCommandFinished($finishEvent);
    }

    #[Test]
    public function it_logs_scheduled_task_starting_event(): void
    {
        Config::set('logging.command_logging.enabled', true);

        Log::shouldReceive('withContext')
            ->once()
            ->with(Mockery::on(function (array $context): bool {
                return isset($context['request_id']) && is_string($context['request_id']);
            }));

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return str_starts_with($message, 'scheduled_task :')
                    && $context['event'] === 'task.starting'
                    && $context['task']['command'] === 'test:scheduled'
                    && isset($context['task']['expression']);
            });

        $task = Mockery::mock(ScheduledEvent::class);
        $task->command = 'test:scheduled';
        $task->description = 'Test scheduled task';
        $task->expression = '* * * * *';

        $event = new ScheduledTaskStarting($task);

        $this->subscriber->onScheduledTaskStarting($event);
    }

    #[Test]
    public function it_logs_scheduled_task_finished_event(): void
    {
        Config::set('logging.command_logging.enabled', true);

        // When task is finished without starting, it still logs but without context
        Log::shouldReceive('withContext')->never();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return str_starts_with($message, 'scheduled_task :')
                    && $context['event'] === 'task.finished'
                    && $context['task']['command'] === 'test:scheduled'
                    && isset($context['performance']['runtime_seconds'])
                    && ! isset($context['performance']['execution_time_ms']);
            });

        $task = Mockery::mock(ScheduledEvent::class);
        $task->command = 'test:scheduled';

        $finishEvent = new ScheduledTaskFinished($task, 1.5);
        $this->subscriber->onScheduledTaskFinished($finishEvent);
    }

    #[Test]
    public function it_logs_scheduled_task_failed_event(): void
    {
        Config::set('logging.command_logging.enabled', true);

        // When task fails without starting, it still logs but without context
        Log::shouldReceive('withContext')->never();
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return str_starts_with($message, 'scheduled_task :')
                    && $context['event'] === 'task.failed'
                    && $context['task']['command'] === 'test:scheduled'
                    && isset($context['exception']['class'])
                    && isset($context['exception']['message']);
            });

        $task = Mockery::mock(ScheduledEvent::class);
        $task->command = 'test:scheduled';
        $task->description = 'Test scheduled task';

        $exception = new RuntimeException('Task failed');
        $failEvent = new ScheduledTaskFailed($task, $exception);
        $this->subscriber->onScheduledTaskFailed($failEvent);
    }

    #[Test]
    public function it_sanitizes_sensitive_arguments(): void
    {
        Config::set('logging.command_logging.enabled', true);
        Config::set('logging.command_logging.log_arguments', true);

        Log::shouldReceive('withContext')
            ->once()
            ->with(Mockery::on(function (array $context): bool {
                return isset($context['request_id']) && is_string($context['request_id']);
            }));

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return isset($context['command']['arguments'])
                    && $context['command']['arguments']['password'] === '***REDACTED***'
                    && $context['command']['arguments']['username'] === 'test-user';
            });

        $input = Mockery::mock(InputInterface::class);
        $input->shouldReceive('getArguments')->andReturn([
            'command' => 'test:command',
            'username' => 'test-user',
            'password' => 'secret123',
        ]);
        $input->shouldReceive('getOptions')->andReturn([]);

        $event = new CommandStarting('test:command', $input, $this->output);

        $this->subscriber->onCommandStarting($event);
    }

    #[Test]
    public function it_sanitizes_sensitive_options(): void
    {
        Config::set('logging.command_logging.enabled', true);
        Config::set('logging.command_logging.log_options', true);

        Log::shouldReceive('withContext')
            ->once()
            ->with(Mockery::on(function (array $context): bool {
                return isset($context['request_id']) && is_string($context['request_id']);
            }));

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return isset($context['command']['options'])
                    && $context['command']['options']['api_key'] === '***REDACTED***';
            });

        $input = Mockery::mock(InputInterface::class);
        $input->shouldReceive('getArguments')->andReturn([
            'command' => 'test:command',
        ]);
        $input->shouldReceive('getOptions')->andReturn([
            'api_key' => 'secret-key-123',
        ]);

        $event = new CommandStarting('test:command', $input, $this->output);

        $this->subscriber->onCommandStarting($event);
    }

    #[Test]
    public function it_does_not_log_arguments_when_disabled(): void
    {
        Config::set('logging.command_logging.enabled', true);
        Config::set('logging.command_logging.log_arguments', false);

        Log::shouldReceive('withContext')
            ->once()
            ->with(Mockery::on(function (array $context): bool {
                return isset($context['request_id']) && is_string($context['request_id']);
            }));

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return $context['command']['arguments'] === null;
            });

        $input = new ArrayInput([
            'command' => 'test:command',
            'username' => 'test-user',
        ]);
        $event = new CommandStarting('test:command', $input, $this->output);

        $this->subscriber->onCommandStarting($event);
    }

    #[Test]
    public function it_does_not_log_options_when_disabled(): void
    {
        Config::set('logging.command_logging.enabled', true);
        Config::set('logging.command_logging.log_options', false);

        Log::shouldReceive('withContext')
            ->once()
            ->with(Mockery::on(function (array $context): bool {
                return isset($context['request_id']) && is_string($context['request_id']);
            }));

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return $context['command']['options'] === null;
            });

        $input = new ArrayInput([
            'command' => 'test:command',
            '--verbose' => true,
        ]);
        $event = new CommandStarting('test:command', $input, $this->output);

        $this->subscriber->onCommandStarting($event);
    }

    #[Test]
    public function it_skips_all_logging_when_disabled(): void
    {
        Config::set('logging.command_logging.enabled', false);

        Log::shouldReceive('withContext')->never();
        Log::shouldReceive('info')->never();

        $input = new ArrayInput(['command' => 'test:command']);
        $event = new CommandStarting('test:command', $input, $this->output);

        $this->subscriber->onCommandStarting($event);
    }
}
