<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => ['stderr', 'daily'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', 'Laravel Log'),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        'third-party-apis' => [
            'driver' => 'daily',
            'path' => storage_path('logs/third-party-apis.log'),
            'level' => env('LOG_LEVEL_THIRD_PARTY', 'info'),
            'days' => env('LOG_THIRD_PARTY_DAYS', 14),
            'replace_placeholders' => true,
        ],
        /*
    |--------------------------------------------------------------------------
    | Request Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how HTTP requests are logged by the LogRequest middleware.
    |
    */
        'request_logging' => [
            // Enable or disable request logging
            'enabled' => env('LOG_REQUESTS', true),

            // Log request body parameters (can be verbose)
            'log_body' => env('LOG_REQUEST_BODY', false),

            // Paths to exclude from logging (supports wildcards)
            'excluded_paths' => [
                'health-check',
                'health',
                'metrics',
                'api/health',
                'api/v1/health', // ECS/ELB health check endpoint
                'telescope*',
                'horizon*',
                'log-viewer*', // Exclude ALL log-viewer routes and API calls
                'livewire*', // Exclude Livewire assets and requests
            ],

            // Route names to exclude from logging (exact match)
            'excluded_route_names' => [
                'health-check',
                'metrics',
                'sanctum.csrf-cookie',
            ],
        ],
    ],

    /*
  |--------------------------------------------------------------------------
  | Job Logging Configuration
  |--------------------------------------------------------------------------
  |
  | Configure how queue jobs are logged by the LogJobSubscriber.
  |
  */
    'job_logging' => [
        // Enable or disable job logging
        'enabled' => env('LOG_JOBS', true),

        // Channel to use for job logging
        'channel' => env('LOG_JOBS_CHANNEL', 'stack'),

        // Log job payload (can be verbose)
        'log_payload' => env('LOG_JOB_PAYLOAD', false),

        // Log only failures (skip success logs)
        'log_failures_only' => env('LOG_JOB_FAILURES_ONLY', false),

        // Jobs to exclude from logging
        'excluded_jobs' => [
            // 'App\Jobs\HealthCheckJob',
            // 'App\Jobs\MetricsCollectionJob',
        ],

        // Performance thresholds (in milliseconds)
        'slow_threshold_ms' => env('LOG_JOB_SLOW_THRESHOLD', 5000),      // 5 seconds
        'warning_threshold_ms' => env('LOG_JOB_WARNING_THRESHOLD', 10000), // 10 seconds
        'critical_threshold_ms' => env('LOG_JOB_CRITICAL_THRESHOLD', 30000), // 30 seconds
    ],

    /*
  |--------------------------------------------------------------------------
  | Command Logging Configuration
  |--------------------------------------------------------------------------
  |
  | Configure how Artisan commands are logged by the LogCommandSubscriber.
  |
  */
    'command_logging' => [
        // Enable or disable command logging
        'enabled' => env('LOG_COMMANDS', true),

        // Log command arguments (can be verbose)
        'log_arguments' => env('LOG_COMMAND_ARGUMENTS', true),

        // Log command options (can be verbose)
        'log_options' => env('LOG_COMMAND_OPTIONS', true),

        // Commands to exclude from logging
        'excluded_commands' => [
            // 'schedule:run',
            // 'queue:work',
        ],

        // Performance thresholds (in milliseconds)
        'slow_threshold_ms' => env('LOG_COMMAND_SLOW_THRESHOLD', 60000),      // 1 minute
        'warning_threshold_ms' => env('LOG_COMMAND_WARNING_THRESHOLD', 120000), // 2 minutes
        'critical_threshold_ms' => env('LOG_COMMAND_CRITICAL_THRESHOLD', 300000), // 5 minutes
    ],

];
