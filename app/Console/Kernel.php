<?php

namespace App\Console;

use App\Console\Commands\BroadcastPushNotificationCommand;
use App\Console\Commands\DeleteExpiredInvitedUsersCommand;
use App\Console\Commands\Integrations\Wellbeing\WellWo\AnalyzeContentAvailabilityCommand;
use App\Console\Commands\Invoicing\GenerateMonthlyInvoicesCommand;
use App\Console\Commands\Translations\ReconcileTranslationsCommand;
use App\Console\Commands\ValidateLanguageSynchronization;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array<int, class-string>
     */
    protected $commands = [
        BroadcastPushNotificationCommand::class,
        DeleteExpiredInvitedUsersCommand::class,
        ReconcileTranslationsCommand::class,
        ValidateLanguageSynchronization::class,
        AnalyzeContentAvailabilityCommand::class,
        GenerateMonthlyInvoicesCommand::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Delete expired invited users every day at midnight
        $schedule->command('invited-users:delete-expired')
            ->daily()
            ->at('00:00')
            ->appendOutputTo(storage_path('logs/scheduled-tasks.log'));

        // Generate financer metrics daily at 2 AM for all active financers
        $schedule->command('metrics:generate-financer', ['--all' => true])
            ->daily()
            ->at('02:00')
            ->appendOutputTo(storage_path('logs/financer-metrics.log'))
            ->onOneServer()
            ->runInBackground();

        // Validate language synchronization weekly (Sunday at 3 AM)
        $schedule->command('language:validate-sync', ['--report' => true])
            ->weekly()
            ->sundays()
            ->at('03:00')
            ->appendOutputTo(storage_path('logs/language-sync-validation.log'));

        // Translation reconciliation - hourly safety net
        if (config('translations.reconciliation.enabled', true)) {
            $cronExpression = config('translations.reconciliation.cron', '0 * * * *');

            $schedule->command('translations:auto-reconcile')
                ->cron($cronExpression)
                ->appendOutputTo(storage_path('logs/translation-reconciliation.log'))
                ->onOneServer()
                ->runInBackground()
                ->withoutOverlapping(10)
                ->environments(['dev', 'production'])
                ->description('Hourly translation reconciliation safety net');
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        // Load commands from integrations

        require base_path('routes/console.php');
    }
}
