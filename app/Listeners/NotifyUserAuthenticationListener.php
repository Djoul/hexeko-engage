<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserAuthenticated;
use App\Services\SlackService;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotifyUserAuthenticationListener
{
    public function __construct(
        private readonly SlackService $slackService
    ) {}

    public function handle(UserAuthenticated $event): void
    {
        // Check if monitoring is enabled
        if (! config('monitoring.user_login.enabled', false)) {
            return;
        }

        // Get monitored user IDs from config
        $monitoredUserIds = config('monitoring.user_login.user_ids', []);

        if (empty($monitoredUserIds) || ! in_array($event->user->cognito_id, $monitoredUserIds, true)) {
            return;
        }

        $cacheKey = "user_login_notified:{$event->user->cognito_id}";

        // Check throttling
        if ($this->shouldThrottle($cacheKey)) {
            return;
        }

        // Log authentication
        Log::info('Monitored user authenticated', [
            'cognito_id' => $event->user->cognito_id,
            'email' => $event->user->email,
            'timestamp' => $event->timestamp,
            'user_agent' => request()->header('User-Agent'),
            'ip_address' => request()->ip(),
        ]);

        // Send Slack notification
        try {
            $this->sendSlackNotification($event);
        } catch (Exception $e) {
            Log::error('Failed to send Slack notification for user authentication', [
                'cognito_id' => $event->user->cognito_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            // Set throttle cache even on failure to prevent spam
            $this->setThrottle($cacheKey);
        }
    }

    private function shouldThrottle(string $cacheKey): bool
    {
        $lastNotified = Cache::get($cacheKey);

        if ($lastNotified === null) {
            return false;
        }

        $throttleMinutes = config('monitoring.user_login.throttle_minutes', 5);
        $lastNotifiedTime = is_numeric($lastNotified) ? (int) $lastNotified : 0;
        $throttlePeriod = now()->subMinutes($throttleMinutes)->timestamp;

        return $lastNotifiedTime > $throttlePeriod;
    }

    private function setThrottle(string $cacheKey): void
    {
        $throttleMinutes = config('monitoring.user_login.throttle_minutes', 5);

        Cache::put(
            $cacheKey,
            now()->timestamp,
            now()->addMinutes($throttleMinutes)
        );
    }

    private function sendSlackNotification(UserAuthenticated $event): void
    {
        $user = $event->user;
        $slackChannel = config('monitoring.user_login.slack_channel', 'up-engage-tech');

        $message = sprintf(
            "🔐 *Monitored User Login Detected*\n\n".
            "• *User:* %s %s\n".
            "• *Email:* %s\n".
            "• *Cognito ID:* `%s`\n".
            "• *Time:* %s\n".
            "• *IP:* %s\n".
            '• *User Agent:* %s',
            $user->first_name ?? 'N/A',
            $user->last_name ?? 'N/A',
            $user->email,
            $user->cognito_id,
            now()->format('Y-m-d H:i:s T'),
            request()->ip() ?? 'Unknown',
            request()->header('User-Agent') ?? 'Unknown'
        );

        $this->slackService->sendToPublicChannel($message, $slackChannel);
    }
}
