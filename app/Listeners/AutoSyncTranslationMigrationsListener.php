<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\TranslationMigrations\AutoProcessTranslationMigrationJob;
use App\Services\EnvironmentService;
use App\Services\SlackService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Throwable;

class AutoSyncTranslationMigrationsListener implements ShouldQueue
{
    /**
     * @var array<int, string>
     */
    private const INTERFACES = ['mobile', 'web_financer', 'web_beneficiary'];

    private const SLACK_CHANNEL = '#up-engage-tech';

    public function __construct(
        private readonly EnvironmentService $environmentService,
        private readonly SlackService $slackService
    ) {}

    public function handle(MigrationsEnded $event): void
    {
        if ($this->shouldSkip()) {
            return;
        }

        if ($this->isThrottled()) {
            $message = sprintf(
                'Auto-sync skipped (throttle actif) on %s environment.',
                $this->environmentService->getCurrentEnvironment()
            );

            Log::info($message);
            $this->slackService->sendToPublicChannel($message, self::SLACK_CHANNEL);

            return;
        }

        $this->dispatch();
        $this->markReconciliation();
    }

    private function shouldSkip(): bool
    {
        $allowInTests = filter_var(env('ALLOW_AUTO_SYNC_LISTENER_TESTING', false), FILTER_VALIDATE_BOOL);

        if ((defined('PHPUNIT_RUNNING') || app()->runningUnitTests()) && ! $allowInTests) {
            return true;
        }

        if (! $this->environmentService->shouldAutoSync()) {
            Log::info('Auto-sync skipped because environment is not eligible.', [
                'environment' => $this->environmentService->getCurrentEnvironment(),
            ]);

            return true;
        }

        $env = $this->environmentService->getCurrentEnvironment();

        if (in_array($env, ['local', 'staging'], true)) {
            Log::info("Skipping auto-sync on {$env} environment.");

            return true;
        }

        return false;
    }

    private function isThrottled(): bool
    {
        $cacheKey = 'last_reconciliation_listener';
        $throttleSeconds = (int) Config::get('translations.reconciliation.throttle_seconds', 300);

        $lastReconciliation = Cache::get($cacheKey);

        if (! $lastReconciliation instanceof DateTimeInterface) {
            return false;
        }

        return Date::now()->diffInSeconds($lastReconciliation) < $throttleSeconds;
    }

    private function markReconciliation(): void
    {
        $throttleSeconds = (int) Config::get('translations.reconciliation.throttle_seconds', 300);
        Cache::put('last_reconciliation_listener', Date::now(), $throttleSeconds);
    }

    private function dispatch(): void
    {
        Log::info('Translation migration automation triggered after migrations', [
            'interfaces' => self::INTERFACES,
            'environment' => $this->environmentService->getCurrentEnvironment(),
        ]);

        $queueConnection = config('queue.default');
        $queueName = config("queue.connections.{$queueConnection}.queue", 'default');
        $queueNameStr = is_string($queueName) ? $queueName : 'default';

        try {
            foreach (self::INTERFACES as $interface) {
                AutoProcessTranslationMigrationJob::dispatch($interface)
                    ->onQueue($queueNameStr);
            }

            $this->slackService->sendToPublicChannel(
                sprintf(
                    '✅ Auto-sync déclenchée sur %s (%d jobs dispatchés).',
                    $this->environmentService->getCurrentEnvironment(),
                    count(self::INTERFACES)
                ),
                self::SLACK_CHANNEL
            );
        } catch (Throwable $exception) {
            $message = sprintf(
                '❌ Auto-sync échouée sur %s : %s',
                $this->environmentService->getCurrentEnvironment(),
                $exception->getMessage()
            );

            Log::error($message, ['exception' => $exception]);
            $this->slackService->sendToPublicChannel($message, self::SLACK_CHANNEL);

            throw $exception;
        }
    }
}
