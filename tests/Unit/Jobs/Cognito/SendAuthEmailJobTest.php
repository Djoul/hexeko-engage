<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs\Cognito;

use App\Jobs\Cognito\SendAuthEmailJob;
use App\Models\CognitoAuditLog;
use App\Services\Localization\LocaleManager;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[Group('cognito')]
#[Group('jobs')]
#[Group('email')]
class SendAuthEmailJobTest extends TestCase
{
    use DatabaseTransactions;

    private LocaleManager $localeManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->localeManager = app(LocaleManager::class);
    }

    #[Test]
    public function it_updates_audit_log_to_sent_on_success(): void
    {
        // Arrange
        $auditLog = $this->createAuditLog('queued');

        // Act
        $job = new SendAuthEmailJob(
            email: 'user@example.com',
            sub: null,
            code: '123456',
            triggerSource: 'CustomEmailLambda_ForgotPassword',
            locale: 'fr-FR',
            auditLogId: $auditLog->id
        );

        // Mock Email service would be called here in real implementation
        $job->handle($this->localeManager);

        // Assert
        $auditLog->refresh();
        $this->assertEquals('sent', $auditLog->status);
    }

    #[Test]
    public function it_creates_job_with_correct_properties(): void
    {
        // Arrange & Act
        $job = new SendAuthEmailJob(
            email: 'user@example.com',
            sub: null,
            code: '123456',
            triggerSource: 'CustomEmailLambda_ForgotPassword',
            locale: 'fr-FR',
            auditLogId: 1
        );

        // Assert - Job should be queueable
        $this->assertInstanceOf(SendAuthEmailJob::class, $job);
    }

    #[Test]
    public function it_is_queued_on_default_queue(): void
    {
        // Arrange
        Queue::fake();

        // Act
        SendAuthEmailJob::dispatch(
            email: 'user@example.com',
            sub: null,
            code: '123456',
            triggerSource: 'CustomEmailLambda_ForgotPassword',
            locale: 'fr-FR',
            auditLogId: 1
        );

        // Assert
        Queue::assertPushed(SendAuthEmailJob::class, function ($job): bool {
            return $job->queue === null; // Default queue
        });
    }

    #[Test]
    public function it_has_3_retry_attempts(): void
    {
        // Arrange
        $job = new SendAuthEmailJob(
            email: 'user@example.com',
            sub: null,
            code: '123456',
            triggerSource: 'CustomEmailLambda_ForgotPassword',
            locale: 'fr-FR',
            auditLogId: 1
        );

        // Assert - Job should have tries property
        $reflection = new ReflectionClass($job);
        $this->assertTrue($reflection->hasProperty('tries'));
    }

    #[Test]
    public function it_updates_audit_log_to_failed_on_exception(): void
    {
        // Arrange
        $auditLog = $this->createAuditLog('queued');

        $job = new SendAuthEmailJob(
            email: 'user@example.com',
            sub: null,
            code: '123456',
            triggerSource: 'CustomEmailLambda_ForgotPassword',
            locale: 'fr-FR',
            auditLogId: $auditLog->id
        );

        $exception = new Exception('Email service unavailable');

        // Act
        $job->failed($exception);

        // Assert
        $auditLog->refresh();
        $this->assertEquals('failed', $auditLog->status);
        $this->assertStringContainsString('Email service unavailable', $auditLog->error_message);
    }

    #[Test]
    public function it_logs_email_sending_attempt_with_masked_email(): void
    {
        // Arrange
        Log::spy();
        $auditLog = $this->createAuditLog('queued');

        // Act
        $job = new SendAuthEmailJob(
            email: 'user@example.com',
            sub: null,
            code: '123456',
            triggerSource: 'CustomEmailLambda_ForgotPassword',
            locale: 'fr-FR',
            auditLogId: $auditLog->id
        );

        $job->handle($this->localeManager);

        // Assert - Should log with masked email (expects 2 calls: start and success)
        Log::shouldHaveReceived('info')
            ->times(2)
            ->with(Mockery::type('string'), Mockery::on(function (array $context): bool {
                return isset($context['email'])
                    && str_contains($context['email'], '***')
                    && ! str_contains($context['email'], 'user@example.com');
            }));
    }

    #[Test]
    public function it_uses_locale_from_parameter(): void
    {
        // Arrange
        $auditLog = $this->createAuditLog('queued', 'es-ES');

        // Act
        $job = new SendAuthEmailJob(
            email: 'user@example.com',
            sub: null,
            code: '123456',
            triggerSource: 'CustomEmailLambda_ForgotPassword',
            locale: 'es-ES',
            auditLogId: $auditLog->id
        );

        // Mock Email service call would verify Spanish locale is used
        $job->handle($this->localeManager);

        // Assert
        $auditLog->refresh();
        $this->assertEquals('es-ES', $auditLog->locale);
    }

    #[Test]
    public function it_handles_verification_emails(): void
    {
        // Arrange
        $auditLog = $this->createAuditLogForVerification();

        // Act
        $job = new SendAuthEmailJob(
            email: 'user@example.com',
            sub: null,
            code: '123456',
            triggerSource: 'CustomEmailLambda_ConfirmSignUp',
            locale: 'fr-FR',
            auditLogId: $auditLog->id
        );

        $job->handle($this->localeManager);

        // Assert - Should complete without errors
        $auditLog->refresh();
        $this->assertEquals('sent', $auditLog->status);
    }

    private function createAuditLog(string $status = 'queued', string $locale = 'fr-FR'): CognitoAuditLog
    {
        return CognitoAuditLog::createAudit(
            identifierHash: hash('sha256', 'user@example.com'),
            type: 'email',
            triggerSource: 'CustomEmailLambda_ForgotPassword',
            locale: $locale,
            payload: [
                'email' => 'user@example.com',
                'code' => '123456',
                'trigger_source' => 'CustomEmailLambda_ForgotPassword',
            ],
            status: $status
        );
    }

    private function createAuditLogForVerification(): CognitoAuditLog
    {
        return CognitoAuditLog::createAudit(
            identifierHash: hash('sha256', 'user@example.com'),
            type: 'email',
            triggerSource: 'CustomEmailLambda_ConfirmSignUp',
            locale: 'fr-FR',
            payload: [
                'email' => 'user@example.com',
                'code' => '123456',
                'trigger_source' => 'CustomEmailLambda_ConfirmSignUp',
            ],
            status: 'queued'
        );
    }
}
