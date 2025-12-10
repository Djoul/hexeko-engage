<?php

namespace App\Subscribers;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogCommandSubscriber
{
    /**
     * Store command start times and request IDs.
     *
     * @var array<string, array{start_time: float, request_id: string}>
     */
    private array $commandStartTimes = [];

    /**
     * Subscribe to command events.
     *
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            CommandStarting::class => 'onCommandStarting',
            CommandFinished::class => 'onCommandFinished',
            ScheduledTaskStarting::class => 'onScheduledTaskStarting',
            ScheduledTaskFinished::class => 'onScheduledTaskFinished',
            ScheduledTaskFailed::class => 'onScheduledTaskFailed',
        ];
    }

    /**
     * Handle command starting event.
     */
    public function onCommandStarting(CommandStarting $event): void
    {
        if (! $this->shouldLogCommand($event->command)) {
            return;
        }

        $requestId = $this->generateRequestId();
        $commandKey = $this->getCommandKey($event->command);

        // Store start time and request ID
        $this->commandStartTimes[$commandKey] = [
            'start_time' => microtime(true),
            'request_id' => $requestId,
        ];

        Log::withContext([
            'request_id' => $requestId,
        ]);

        $logTitle = sprintf(' %s (starting)', $event->command);

        Log::info('command :'.$logTitle, [
            'event' => 'command.starting',
            'command' => [
                'name' => $event->command,
                'arguments' => config('logging.command_logging.log_arguments', true)
                    ? $this->sanitizeArguments($event->input->getArguments())
                    : null,
                'options' => config('logging.command_logging.log_options', true)
                    ? $this->sanitizeOptions($event->input->getOptions())
                    : null,
            ],
            'context' => [
                'user' => $this->getCurrentUser(),
                'environment' => app()->environment(),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'working_directory' => getcwd(),
            ],
            'request_id' => $requestId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Handle command finished event.
     */
    public function onCommandFinished(CommandFinished $event): void
    {
        if (! $this->shouldLogCommand($event->command)) {
            return;
        }

        $commandKey = $this->getCommandKey($event->command);

        if (! array_key_exists($commandKey, $this->commandStartTimes)) {
            return;
        }

        $executionTime = round((microtime(true) - $this->commandStartTimes[$commandKey]['start_time']) * 1000, 2);
        $logLevel = $this->getLogLevelForCommand($event->exitCode, $executionTime);

        Log::withContext([
            'request_id' => $this->commandStartTimes[$commandKey]['request_id'],
        ]);

        $status = $event->exitCode === 0 ? 'success' : 'failed';
        $logTitle = sprintf('[COMMAND] %s (%s, %sms)', $event->command, $status, number_format($executionTime, 2));

        Log::$logLevel('command :'.$logTitle, [
            'event' => 'command.finished',
            'command' => [
                'name' => $event->command,
                'exit_code' => $event->exitCode,
                'status' => $status,
            ],
            'performance' => [
                'execution_time_ms' => $executionTime,
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'is_slow' => $executionTime > config('logging.command_logging.slow_threshold_ms', 60000),
            ],
            'request_id' => $this->commandStartTimes[$commandKey]['request_id'],
            'timestamp' => now()->toIso8601String(),
        ]);

        // Clean up memory
        unset($this->commandStartTimes[$commandKey]);
    }

    /**
     * Handle scheduled task starting event.
     */
    public function onScheduledTaskStarting(ScheduledTaskStarting $event): void
    {
        if (! config('logging.command_logging.enabled', true)) {
            return;
        }

        $requestId = $this->generateRequestId();
        $taskKey = $this->getTaskKey($event->task->command);

        // Store start time and request ID
        $this->commandStartTimes[$taskKey] = [
            'start_time' => microtime(true),
            'request_id' => $requestId,
        ];

        Log::withContext([
            'request_id' => $requestId,
        ]);

        $description = $event->task->description ?: $event->task->command;
        $logTitle = sprintf('[SCHEDULED] %s (starting, cron: %s)', $description, $event->task->expression);

        Log::info('scheduled_task :'.$logTitle, [
            'event' => 'task.starting',
            'task' => [
                'command' => $event->task->command,
                'description' => $event->task->description,
                'expression' => $event->task->expression,
            ],
            'request_id' => $requestId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Handle scheduled task finished event.
     */
    public function onScheduledTaskFinished(ScheduledTaskFinished $event): void
    {
        if (! config('logging.command_logging.enabled', true)) {
            return;
        }

        $taskKey = $this->getTaskKey($event->task->command);

        if (array_key_exists($taskKey, $this->commandStartTimes)) {
            Log::withContext([
                'request_id' => $this->commandStartTimes[$taskKey]['request_id'],
            ]);

            $executionTime = round((microtime(true) - $this->commandStartTimes[$taskKey]['start_time']) * 1000, 2);
            $logTitle = sprintf('[SCHEDULED] %s (finished, %ss)', $event->task->command, number_format($event->runtime, 2));

            Log::info('scheduled_task :'.$logTitle, [
                'event' => 'task.finished',
                'task' => [
                    'command' => $event->task->command,
                ],
                'performance' => [
                    'runtime_seconds' => $event->runtime,
                    'execution_time_ms' => $executionTime,
                    'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                ],
                'request_id' => $this->commandStartTimes[$taskKey]['request_id'],
                'timestamp' => now()->toIso8601String(),
            ]);

            // Clean up memory
            unset($this->commandStartTimes[$taskKey]);
        } else {
            $logTitle = sprintf('[SCHEDULED] %s (finished, %ss)', $event->task->command, number_format($event->runtime, 2));

            Log::info('scheduled_task :'.$logTitle, [
                'event' => 'task.finished',
                'task' => [
                    'command' => $event->task->command,
                ],
                'performance' => [
                    'runtime_seconds' => $event->runtime,
                ],
                'timestamp' => now()->toIso8601String(),
            ]);
        }
    }

    /**
     * Handle scheduled task failed event.
     */
    public function onScheduledTaskFailed(ScheduledTaskFailed $event): void
    {
        if (! config('logging.command_logging.enabled', true)) {
            return;
        }

        $taskKey = $this->getTaskKey($event->task->command);

        if (array_key_exists($taskKey, $this->commandStartTimes)) {
            Log::withContext([
                'request_id' => $this->commandStartTimes[$taskKey]['request_id'],
            ]);

            // Clean up memory
            unset($this->commandStartTimes[$taskKey]);
        }

        $description = $event->task->description ?: $event->task->command;
        $logTitle = sprintf('[SCHEDULED] %s (failed: %s)', $description, $event->exception->getMessage());

        Log::error('scheduled_task :'.$logTitle, [
            'event' => 'task.failed',
            'task' => [
                'command' => $event->task->command,
                'description' => $event->task->description,
            ],
            'exception' => [
                'class' => get_class($event->exception),
                'message' => $event->exception->getMessage(),
                'file' => $event->exception->getFile(),
                'line' => $event->exception->getLine(),
                'trace' => config('app.debug') ? $event->exception->getTraceAsString() : null,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Sanitize command arguments.
     *
     * @param  array<mixed, mixed>  $arguments
     * @return array<mixed, mixed>
     */
    private function sanitizeArguments(array $arguments): array
    {
        return collect($arguments)->map(function ($value, $key) {
            if ($this->isSensitive($key)) {
                return '***REDACTED***';
            }

            if (is_array($value)) {
                return $this->sanitizeArguments($value);
            }

            return $value;
        })->toArray();
    }

    /**
     * Sanitize command options.
     *
     * @param  array<mixed, mixed>  $options
     * @return array<mixed, mixed>
     */
    private function sanitizeOptions(array $options): array
    {
        return collect($options)->map(function ($value, $key) {
            if ($this->isSensitive($key)) {
                return '***REDACTED***';
            }

            if (is_array($value)) {
                return $this->sanitizeOptions($value);
            }

            return $value;
        })->toArray();
    }

    /**
     * Check if a key contains sensitive information.
     */
    private function isSensitive(int|string $key): bool
    {
        if (! is_string($key)) {
            return false;
        }

        $sensitive = [
            'password',
            'token',
            'secret',
            'api_key',
            'api_secret',
            'database',
            'db_password',
            'access_token',
            'refresh_token',
        ];

        foreach ($sensitive as $word) {
            if (Str::contains(strtolower($key), $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the command should be logged.
     */
    private function shouldLogCommand(?string $command): bool
    {
        if (! config('logging.command_logging.enabled', true)) {
            return false;
        }

        if (in_array($command, [null, '', '0'], true)) {
            return false;
        }

        /** @var array<int, string> $excludedCommands */
        $excludedCommands = config('logging.command_logging.excluded_commands', []);

        return ! in_array($command, $excludedCommands, true);
    }

    /**
     * Get a unique key for a command.
     */
    private function getCommandKey(?string $command): string
    {
        return md5(($command ?? 'unknown').microtime().getmypid());
    }

    /**
     * Get a unique key for a scheduled task.
     */
    private function getTaskKey(?string $command): string
    {
        return md5(($command ?? 'unknown').microtime().getmypid().'scheduled');
    }

    /**
     * Get the current system user.
     */
    private function getCurrentUser(): string
    {
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $userInfo = posix_getpwuid(posix_geteuid());

            return $userInfo['name'] ?? 'unknown';
        }

        $user = config('app.user', 'unknown');

        return is_string($user) ? $user : 'unknown';
    }

    /**
     * Get log level based on command exit code and execution time.
     */
    private function getLogLevelForCommand(int $exitCode, float $executionTimeMs): string
    {
        // Error if command failed
        if ($exitCode !== 0) {
            return 'error';
        }

        $criticalThreshold = config('logging.command_logging.critical_threshold_ms', 300000); // 5 minutes
        $warningThreshold = config('logging.command_logging.warning_threshold_ms', 120000);   // 2 minutes

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
