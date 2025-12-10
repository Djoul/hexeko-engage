<?php

declare(strict_types=1);

namespace Tests\Unit\Commands\Cognito;

use App\Models\CognitoAuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('cognito')]
#[Group('commands')]
#[Group('monitoring')]
class MonitorCognitoFallbackRateTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_computes_failure_rate_correctly(): void
    {
        // Arrange - Create audit logs with low failure rate (< 5%)
        $this->createAuditLogs('sms', ['sent' => 98, 'failed' => 2]); // 2% failure
        $this->createAuditLogs('email', ['sent' => 99, 'failed' => 1]); // 1% failure

        // Act
        $exitCode = Artisan::call('cognito:monitor-fallback', ['--no-alert' => true]);

        // Assert
        $output = Artisan::output();
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('All systems nominal', $output);
        $this->assertStringContainsString('100', $output); // Total SMS
        $this->assertStringContainsString('100', $output); // Total Email
    }

    #[Test]
    public function it_alerts_when_threshold_exceeded(): void
    {
        // Arrange - Create audit logs with high failure rate
        $this->createAuditLogs('sms', ['sent' => 3, 'failed' => 7]);
        $this->createAuditLogs('email', ['sent' => 4, 'failed' => 6]);

        // Act
        $exitCode = Artisan::call('cognito:monitor-fallback', ['--no-alert' => true]);

        // Assert
        $output = Artisan::output();
        $this->assertEquals(1, $exitCode); // Exit with error code
        $this->assertStringContainsString('exceeds threshold', $output);
        $this->assertStringContainsString('65%', $output); // 13/20 = 65%
    }

    #[Test]
    public function it_sends_slack_alert_when_enabled(): void
    {
        // Arrange
        config(['services.slack.cognito_alerts_webhook' => 'https://hooks.slack.com/test']);
        Http::fake(['https://hooks.slack.com/test' => Http::response('ok', 200)]);

        $this->createAuditLogs('sms', ['sent' => 3, 'failed' => 7]);
        $this->createAuditLogs('email', ['sent' => 4, 'failed' => 6]);

        // Act
        $exitCode = Artisan::call('cognito:monitor-fallback');

        // Assert
        $output = Artisan::output();
        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Slack alert sent successfully', $output);

        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);

            return $request->url() === 'https://hooks.slack.com/test'
                && isset($body['text'])
                && str_contains($body['text'], 'Cognito Notification Failure Alert');
        });
    }

    #[Test]
    public function it_handles_missing_slack_webhook(): void
    {
        // Arrange
        config(['services.slack.cognito_alerts_webhook' => null]);

        $this->createAuditLogs('sms', ['sent' => 3, 'failed' => 7]);

        // Act
        $exitCode = Artisan::call('cognito:monitor-fallback');

        // Assert
        $output = Artisan::output();
        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Slack webhook not configured', $output);
    }

    #[Test]
    public function it_accepts_custom_hours_parameter(): void
    {
        // Arrange - Create audit logs from 2 hours ago
        $this->createAuditLogs('sms', ['sent' => 5, 'failed' => 5], now()->subHours(2));

        // Act - Monitor last 3 hours (should include our logs)
        Artisan::call('cognito:monitor-fallback', [
            '--hours' => 3,
            '--no-alert' => true,
        ]);

        // Assert
        $output = Artisan::output();
        $this->assertStringContainsString('Last 3 hours', $output);
        $this->assertStringContainsString('10', $output); // Should see 10 total SMS
    }

    #[Test]
    public function it_accepts_custom_threshold_parameter(): void
    {
        // Arrange - Create audit logs with 30% failure rate
        $this->createAuditLogs('sms', ['sent' => 7, 'failed' => 3]);

        // Act - Set threshold to 20% (should trigger alert)
        $exitCode = Artisan::call('cognito:monitor-fallback', [
            '--threshold' => 20,
            '--no-alert' => true,
        ]);

        // Assert
        $output = Artisan::output();
        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('exceeds threshold (20%)', $output);
    }

    #[Test]
    public function it_displays_stats_table_with_color_coding(): void
    {
        // Arrange
        $this->createAuditLogs('sms', ['sent' => 8, 'failed' => 2]);
        $this->createAuditLogs('email', ['sent' => 9, 'failed' => 1]);

        // Act
        Artisan::call('cognito:monitor-fallback', ['--no-alert' => true]);

        // Assert
        $output = Artisan::output();
        $this->assertStringContainsString('Type', $output);
        $this->assertStringContainsString('Total', $output);
        $this->assertStringContainsString('Sent', $output);
        $this->assertStringContainsString('Failed', $output);
        $this->assertStringContainsString('Failure Rate', $output);
        $this->assertStringContainsString('SMS', $output);
        $this->assertStringContainsString('Email', $output);
        $this->assertStringContainsString('TOTAL', $output);
    }

    #[Test]
    public function it_handles_zero_audit_logs_gracefully(): void
    {
        // Arrange - No audit logs created

        // Act
        $exitCode = Artisan::call('cognito:monitor-fallback', ['--no-alert' => true]);

        // Assert
        $output = Artisan::output();
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('0', $output);
        $this->assertStringContainsString('0%', $output);
    }

    #[Test]
    public function it_only_counts_logs_within_time_period(): void
    {
        // Arrange - Create old audit logs (25 hours ago - should be excluded)
        $this->createAuditLogs('sms', ['sent' => 5, 'failed' => 5], now()->subHours(25));

        // Create recent audit logs (1 hour ago - should be included, with low failure rate)
        $this->createAuditLogs('email', ['sent' => 98, 'failed' => 2], now()->subHours(1)); // 2% failure

        // Act - Monitor last 24 hours
        $exitCode = Artisan::call('cognito:monitor-fallback', ['--no-alert' => true]);

        // Assert
        $output = Artisan::output();
        $this->assertEquals(0, $exitCode);

        // Should only see the recent email logs, not the old SMS logs
        $this->assertStringContainsString('100', $output); // Email total should be 100
    }

    /**
     * @param  array{sent: int, failed: int}  $counts
     */
    private function createAuditLogs(string $type, array $counts, ?Carbon $createdAt = null): void
    {
        $createdAt = $createdAt ?? now();

        // Create 'sent' audit logs
        for ($i = 0; $i < $counts['sent']; $i++) {
            $auditLog = CognitoAuditLog::createAudit(
                identifierHash: hash('sha256', "test{$type}{$i}@example.com"),
                type: $type,
                triggerSource: "Custom{$type}Lambda_Test",
                locale: 'fr-FR',
                payload: ['email' => "test{$type}{$i}@example.com", 'code' => '123456'],
                status: 'sent'
            );
            $auditLog->created_at = $createdAt;
            $auditLog->save();
        }

        // Create 'failed' audit logs
        for ($i = 0; $i < $counts['failed']; $i++) {
            $auditLog = CognitoAuditLog::createAudit(
                identifierHash: hash('sha256', "failed{$type}{$i}@example.com"),
                type: $type,
                triggerSource: "Custom{$type}Lambda_Test",
                locale: 'fr-FR',
                payload: ['email' => "failed{$type}{$i}@example.com", 'code' => '123456'],
                status: 'failed'
            );
            $auditLog->created_at = $createdAt;
            $auditLog->save();
        }
    }
}
