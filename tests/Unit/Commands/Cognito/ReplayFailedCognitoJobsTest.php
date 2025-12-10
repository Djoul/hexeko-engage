<?php

declare(strict_types=1);

namespace Tests\Unit\Commands\Cognito;

use App\Jobs\Cognito\SendAuthEmailJob;
use App\Jobs\Cognito\SendSmsJob;
use App\Models\CognitoAuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('cognito')]
#[Group('dlq')]
#[Group('commands')]
class ReplayFailedCognitoJobsTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_replays_failed_sms_jobs(): void
    {
        // Arrange
        Queue::fake();

        $failedAuditLog = CognitoAuditLog::createAudit(
            identifierHash: hash('sha256', 'user@example.com'),
            type: 'sms',
            triggerSource: 'CustomSMSLambda_SignUp',
            locale: 'fr-FR',
            payload: [
                'email' => 'user@example.com',
                'sub' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
                'code' => '123456',
                'trigger_source' => 'CustomSMSLambda_SignUp',
            ],
            status: 'failed',
            sourceIp: '127.0.0.1'
        );

        // Act
        $this->artisan('cognito:replay-failed', ['--type' => 'sms', '--limit' => 10])
            ->expectsConfirmation('Do you want to replay these jobs?', 'yes')
            ->assertSuccessful();

        // Assert
        Queue::assertPushed(SendSmsJob::class, function (SendSmsJob $job) use ($failedAuditLog): bool {
            return $job->auditLogId === $failedAuditLog->id;
        });

        $failedAuditLog->refresh();
        $this->assertEquals('queued', $failedAuditLog->status);
    }

    #[Test]
    public function it_replays_failed_email_jobs(): void
    {
        // Arrange
        Queue::fake();

        $failedAuditLog = CognitoAuditLog::createAudit(
            identifierHash: hash('sha256', 'user@example.com'),
            type: 'email',
            triggerSource: 'CustomEmailLambda_ForgotPassword',
            locale: 'en-GB',
            payload: [
                'email' => 'user@example.com',
                'sub' => 'b2c3d4e5-f6g7-8901-bcde-fg2345678901',
                'code' => '654321',
                'trigger_source' => 'CustomEmailLambda_ForgotPassword',
            ],
            status: 'failed',
            sourceIp: '127.0.0.1'
        );

        // Act
        $this->artisan('cognito:replay-failed', ['--type' => 'email', '--limit' => 10])
            ->expectsConfirmation('Do you want to replay these jobs?', 'yes')
            ->assertSuccessful();

        // Assert
        Queue::assertPushed(SendAuthEmailJob::class, function (SendAuthEmailJob $job) use ($failedAuditLog): bool {
            return $job->auditLogId === $failedAuditLog->id;
        });

        $failedAuditLog->refresh();
        $this->assertEquals('queued', $failedAuditLog->status);
    }

    #[Test]
    public function it_decrypts_payload_for_replay(): void
    {
        // Arrange
        Queue::fake();

        $originalPayload = [
            'email' => 'user@example.com',
            'sub' => 'test-sub-uuid-12345',
            'code' => '999888',
            'trigger_source' => 'CustomSMSLambda_Authentication',
        ];

        CognitoAuditLog::createAudit(
            identifierHash: hash('sha256', 'user@example.com'),
            type: 'sms',
            triggerSource: 'CustomSMSLambda_Authentication',
            locale: 'de-DE',
            payload: $originalPayload,
            status: 'failed',
            sourceIp: '192.168.1.1'
        );

        // Act
        $this->artisan('cognito:replay-failed', ['--type' => 'sms', '--limit' => 5])
            ->expectsConfirmation('Do you want to replay these jobs?', 'yes')
            ->assertSuccessful();

        // Assert - Job dispatched with decrypted data
        Queue::assertPushed(SendSmsJob::class, function (SendSmsJob $job) use ($originalPayload): bool {
            return $job->code === $originalPayload['code']
                && $job->identifier === $originalPayload['email']
                && $job->sub === $originalPayload['sub'];
        });
    }

    #[Test]
    public function it_skips_already_successful_jobs(): void
    {
        // Arrange
        Queue::fake();

        CognitoAuditLog::createAudit(
            identifierHash: hash('sha256', 'user@example.com'),
            type: 'sms',
            triggerSource: 'CustomSMSLambda_SignUp',
            locale: 'fr-FR',
            payload: ['email' => 'user@example.com', 'code' => '123456'],
            status: 'sent', // Already successful
            sourceIp: '127.0.0.1'
        );

        // Act
        $this->artisan('cognito:replay-failed', ['--type' => 'sms', '--limit' => 10])
            ->assertSuccessful();

        // Assert - No jobs dispatched
        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_respects_max_parameter(): void
    {
        // Arrange
        Queue::fake();

        // Create 5 failed audit logs
        for ($i = 0; $i < 5; $i++) {
            CognitoAuditLog::createAudit(
                identifierHash: hash('sha256', "user{$i}@example.com"),
                type: 'sms',
                triggerSource: 'CustomSMSLambda_SignUp',
                locale: 'fr-FR',
                payload: ['email' => "user{$i}@example.com", 'code' => '123456'],
                status: 'failed',
                sourceIp: '127.0.0.1'
            );
        }

        // Act - Replay only 3
        $this->artisan('cognito:replay-failed', ['--type' => 'sms', '--limit' => 3])
            ->expectsConfirmation('Do you want to replay these jobs?', 'yes')
            ->assertSuccessful();

        // Assert - Only 3 jobs dispatched
        Queue::assertPushed(SendSmsJob::class, 3);
    }

    #[Test]
    public function it_handles_both_types_when_no_type_specified(): void
    {
        // Arrange
        Queue::fake();

        CognitoAuditLog::createAudit(
            identifierHash: hash('sha256', 'user1@example.com'),
            type: 'sms',
            triggerSource: 'CustomSMSLambda_SignUp',
            locale: 'fr-FR',
            payload: ['email' => 'user1@example.com', 'code' => '123456'],
            status: 'failed',
            sourceIp: '127.0.0.1'
        );

        CognitoAuditLog::createAudit(
            identifierHash: hash('sha256', 'user2@example.com'),
            type: 'email',
            triggerSource: 'CustomEmailLambda_ForgotPassword',
            locale: 'en-GB',
            payload: ['email' => 'user2@example.com', 'code' => '654321'],
            status: 'failed',
            sourceIp: '127.0.0.1'
        );

        // Act
        $this->artisan('cognito:replay-failed', ['--limit' => 10])
            ->expectsConfirmation('Do you want to replay these jobs?', 'yes')
            ->assertSuccessful();

        // Assert
        Queue::assertPushed(SendSmsJob::class, 1);
        Queue::assertPushed(SendAuthEmailJob::class, 1);
    }
}
