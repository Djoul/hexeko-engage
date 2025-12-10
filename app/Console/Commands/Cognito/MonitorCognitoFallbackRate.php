<?php

declare(strict_types=1);

namespace App\Console\Commands\Cognito;

use App\Models\CognitoAuditLog;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MonitorCognitoFallbackRate extends Command
{
    protected $signature = 'cognito:monitor-fallback
                            {--hours=24 : Number of hours to analyze}
                            {--threshold=5 : Failure threshold percentage for alerts}
                            {--no-alert : Disable Slack alerts}';

    protected $description = 'Monitor Cognito notification failure rate and alert if threshold exceeded';

    private const SLACK_ALERT_THRESHOLD = 5.0; // 5%

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $threshold = (float) $this->option('threshold');
        $noAlert = (bool) $this->option('no-alert');

        $this->line('');
        $this->line('📊 <fg=cyan>═══════════════════════════════════════════════════════════════</fg=cyan>');
        $this->line('📊 <fg=cyan>          COGNITO NOTIFICATIONS - FAILURE RATE MONITOR</fg=cyan>');
        $this->line('📊 <fg=cyan>═══════════════════════════════════════════════════════════════</fg=cyan>');
        $this->line('');

        $startTime = now()->subHours($hours);

        $this->info("📅 Analyzing period: Last {$hours} hours (since {$startTime->format('Y-m-d H:i:s')})");
        $this->newLine();

        // Calculate stats for SMS
        $smsStats = $this->calculateStats('sms', $startTime);

        // Calculate stats for Email
        $emailStats = $this->calculateStats('email', $startTime);

        // Display combined stats table
        $this->displayStatsTable($smsStats, $emailStats);

        // Check if alerts should be sent
        $totalFailureRate = $this->calculateTotalFailureRate($smsStats, $emailStats);

        if ($totalFailureRate > $threshold) {
            $this->warn("⚠️  Total failure rate ({$totalFailureRate}%) exceeds threshold ({$threshold}%)");

            if (! $noAlert) {
                $this->sendSlackAlert($smsStats, $emailStats, $totalFailureRate, $hours);
            } else {
                $this->comment('ℹ️  Slack alerts disabled (--no-alert flag)');
            }

            return 1; // Exit with error code
        }

        $this->info("✅ All systems nominal - Failure rate: {$totalFailureRate}%");

        return 0;
    }

    /**
     * @return array{total: int, sent: int, failed: int, queued: int, rate: float}
     */
    private function calculateStats(string $type, Carbon $startTime): array
    {
        $total = CognitoAuditLog::where('type', $type)
            ->where('created_at', '>=', $startTime)
            ->count();

        $sent = CognitoAuditLog::where('type', $type)
            ->where('status', 'sent')
            ->where('created_at', '>=', $startTime)
            ->count();

        $failed = CognitoAuditLog::where('type', $type)
            ->where('status', 'failed')
            ->where('created_at', '>=', $startTime)
            ->count();

        $queued = CognitoAuditLog::where('type', $type)
            ->where('status', 'queued')
            ->where('created_at', '>=', $startTime)
            ->count();

        $rate = $total > 0 ? round(($failed / $total) * 100, 2) : 0.0;

        return [
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'queued' => $queued,
            'rate' => $rate,
        ];
    }

    /**
     * @param  array{total: int, sent: int, failed: int, queued: int, rate: float}  $smsStats
     * @param  array{total: int, sent: int, failed: int, queued: int, rate: float}  $emailStats
     */
    private function displayStatsTable(array $smsStats, array $emailStats): void
    {
        $this->table(
            ['Type', 'Total', 'Sent', 'Failed', 'Queued', 'Failure Rate'],
            [
                [
                    'SMS',
                    number_format($smsStats['total']),
                    number_format($smsStats['sent']),
                    $this->formatFailureCount($smsStats['failed'], $smsStats['rate']),
                    number_format($smsStats['queued']),
                    $this->formatFailureRate($smsStats['rate']),
                ],
                [
                    'Email',
                    number_format($emailStats['total']),
                    number_format($emailStats['sent']),
                    $this->formatFailureCount($emailStats['failed'], $emailStats['rate']),
                    number_format($emailStats['queued']),
                    $this->formatFailureRate($emailStats['rate']),
                ],
                [
                    '<fg=cyan>TOTAL</>',
                    '<fg=cyan>'.number_format($smsStats['total'] + $emailStats['total']).'</>',
                    '<fg=cyan>'.number_format($smsStats['sent'] + $emailStats['sent']).'</>',
                    '<fg=cyan>'.number_format($smsStats['failed'] + $emailStats['failed']).'</>',
                    '<fg=cyan>'.number_format($smsStats['queued'] + $emailStats['queued']).'</>',
                    '<fg=cyan>'.$this->calculateTotalFailureRate($smsStats, $emailStats).'%</>',
                ],
            ]
        );

        $this->newLine();
    }

    /**
     * @param  array{total: int, sent: int, failed: int, queued: int, rate: float}  $smsStats
     * @param  array{total: int, sent: int, failed: int, queued: int, rate: float}  $emailStats
     */
    private function calculateTotalFailureRate(array $smsStats, array $emailStats): float
    {
        $totalNotifications = $smsStats['total'] + $emailStats['total'];
        $totalFailures = $smsStats['failed'] + $emailStats['failed'];

        return $totalNotifications > 0
            ? round(($totalFailures / $totalNotifications) * 100, 2)
            : 0.0;
    }

    private function formatFailureCount(int $failed, float $rate): string
    {
        if ($rate > self::SLACK_ALERT_THRESHOLD) {
            return "<fg=red>{$failed}</>";
        }

        if ($rate > 2.0) {
            return "<fg=yellow>{$failed}</>";
        }

        return (string) $failed;
    }

    private function formatFailureRate(float $rate): string
    {
        if ($rate > self::SLACK_ALERT_THRESHOLD) {
            return "<fg=red>{$rate}%</>";
        }

        if ($rate > 2.0) {
            return "<fg=yellow>{$rate}%</>";
        }

        return "{$rate}%";
    }

    /**
     * @param  array{total: int, sent: int, failed: int, queued: int, rate: float}  $smsStats
     * @param  array{total: int, sent: int, failed: int, queued: int, rate: float}  $emailStats
     */
    private function sendSlackAlert(array $smsStats, array $emailStats, float $totalFailureRate, int $hours): void
    {
        $slackWebhook = config('services.slack.cognito_alerts_webhook');

        if (empty($slackWebhook)) {
            $this->warn('⚠️  Slack webhook not configured (SLACK_COGNITO_ALERTS_WEBHOOK)');

            return;
        }

        $message = [
            'text' => '🚨 Cognito Notification Failure Alert',
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => '🚨 Cognito Notification Failure Alert',
                        'emoji' => true,
                    ],
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Failure rate exceeds threshold*\n".
                            "Current: *{$totalFailureRate}%* | Threshold: *".self::SLACK_ALERT_THRESHOLD."%*\n".
                            "Period: Last {$hours} hours",
                    ],
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*SMS*\n".
                                "Total: {$smsStats['total']}\n".
                                "Failed: {$smsStats['failed']} ({$smsStats['rate']}%)",
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Email*\n".
                                "Total: {$emailStats['total']}\n".
                                "Failed: {$emailStats['failed']} ({$emailStats['rate']}%)",
                        ],
                    ],
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => '⏰ '.now()->format('Y-m-d H:i:s').' | Environment: '.config('app.env'),
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::post($slackWebhook, $message);

            if ($response->successful()) {
                $this->info('✅ Slack alert sent successfully');
            } else {
                $this->error("❌ Failed to send Slack alert: {$response->status()}");
            }
        } catch (Exception $e) {
            $this->error("❌ Exception sending Slack alert: {$e->getMessage()}");
            Log::error('Failed to send Cognito fallback Slack alert', [
                'error' => $e->getMessage(),
                'sms_stats' => $smsStats,
                'email_stats' => $emailStats,
            ]);
        }
    }
}
