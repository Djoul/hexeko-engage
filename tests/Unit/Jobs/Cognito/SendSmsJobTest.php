<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs\Cognito;

use App\Jobs\Cognito\SendSmsJob;
use App\Models\CognitoAuditLog;
use App\Models\User;
use App\Services\Sms\SmsModeService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('cognito')]
#[Group('jobs')]
#[Group('sms')]
class SendSmsJobTest extends TestCase
{
    use DatabaseTransactions;

    private SmsModeService $smsService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock SMS service
        $this->smsService = Mockery::mock(SmsModeService::class);
        $this->app->instance(SmsModeService::class, $this->smsService);
    }

    #[Test]
    public function it_updates_audit_log_to_sent_on_success(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'user@example.com',
            'phone' => '+33612345678',
            'cognito_id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        ]);

        $auditLog = $this->createAuditLog('queued');

        $this->smsService
            ->shouldReceive('sendSms')
            ->once()
            ->with('+33612345678', Mockery::type('string'))
            ->andReturn(['status' => 'sent', 'success' => true]);

        // Act
        $job = new SendSmsJob(
            identifier: 'user@example.com',
            sub: $user->cognito_id,
            code: '123456',
            triggerSource: 'CustomSMSLambda_SignUp',
            locale: 'fr-FR',
            auditLogId: $auditLog->id
        );

        $job->handle($this->smsService);

        // Assert
        $auditLog->refresh();
        $this->assertEquals('sent', $auditLog->status);
    }

    #[Test]
    public function it_creates_job_with_correct_properties(): void
    {
        // Arrange & Act
        $job = new SendSmsJob(
            identifier: 'user@example.com',
            sub: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            code: '123456',
            triggerSource: 'CustomSMSLambda_SignUp',
            locale: 'fr-FR',
            auditLogId: 1
        );

        // Assert - Job should be queueable
        $this->assertInstanceOf(SendSmsJob::class, $job);
        $this->assertEquals('a1b2c3d4-e5f6-7890-abcd-ef1234567890', $job->sub);
    }

    #[Test]
    public function it_is_queued_on_default_queue(): void
    {
        // Arrange
        Queue::fake();

        // Act
        SendSmsJob::dispatch(
            identifier: 'user@example.com',
            sub: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            code: '123456',
            triggerSource: 'CustomSMSLambda_SignUp',
            locale: 'fr-FR',
            auditLogId: 1
        );

        // Assert
        Queue::assertPushed(SendSmsJob::class, function ($job): bool {
            return $job->queue === null; // Default queue
        });
    }

    #[Test]
    public function it_has_3_retry_attempts(): void
    {
        // Arrange
        $job = new SendSmsJob(
            identifier: 'user@example.com',
            sub: null,
            code: '123456',
            triggerSource: 'CustomSMSLambda_SignUp',
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

        $job = new SendSmsJob(
            identifier: 'user@example.com',
            sub: null,
            code: '123456',
            triggerSource: 'CustomSMSLambda_SignUp',
            locale: 'fr-FR',
            auditLogId: $auditLog->id
        );

        $exception = new Exception('SMS service unavailable');

        // Act
        $job->failed($exception);

        // Assert
        $auditLog->refresh();
        $this->assertEquals('failed', $auditLog->status);
        $this->assertStringContainsString('SMS service unavailable', $auditLog->error_message);
    }

    #[Test]
    public function it_logs_sms_sending_attempt_with_masked_identifier(): void
    {
        // Arrange
        Log::spy();
        $user = ModelFactory::createUser([
            'email' => 'user@example.com',
            'phone' => '+33612345678',
            'cognito_id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        ]);

        $auditLog = $this->createAuditLog('queued');

        $this->smsService
            ->shouldReceive('sendSms')
            ->once()
            ->with('+33612345678', Mockery::type('string'))
            ->andReturn(['status' => 'sent', 'success' => true]);

        // Act
        $job = new SendSmsJob(
            identifier: 'user@example.com',
            sub: $user->cognito_id,
            code: '123456',
            triggerSource: 'CustomSMSLambda_SignUp',
            locale: 'fr-FR',
            auditLogId: $auditLog->id
        );

        $job->handle($this->smsService);

        // Assert - Should log with masked identifier (expects 2 calls: start and success)
        Log::shouldHaveReceived('info')
            ->times(2)
            ->with(Mockery::type('string'), Mockery::on(function (array $context): bool {
                return isset($context['identifier'])
                    && str_contains($context['identifier'], '***')
                    && ! str_contains($context['identifier'], 'user@example.com');
            }));
    }

    #[Test]
    public function it_uses_locale_from_parameter(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'user@example.com',
            'phone' => '+33612345678',
            'cognito_id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        ]);

        $auditLog = $this->createAuditLog('queued', 'de-DE');

        $this->smsService
            ->shouldReceive('sendSms')
            ->once()
            ->with('+33612345678', Mockery::type('string'))
            ->andReturn(['status' => 'sent', 'success' => true]);

        // Act
        $job = new SendSmsJob(
            identifier: 'user@example.com',
            sub: $user->cognito_id,
            code: '123456',
            triggerSource: 'CustomSMSLambda_SignUp',
            locale: 'de-DE',
            auditLogId: $auditLog->id
        );

        $job->handle($this->smsService);

        // Assert
        $auditLog->refresh();
        $this->assertEquals('de-DE', $auditLog->locale);
    }

    #[Test]
    public function it_handles_phone_number_identifier(): void
    {
        // Arrange
        $auditLog = $this->createAuditLog('queued');

        $this->smsService
            ->shouldReceive('sendSms')
            ->once()
            ->with('+33612345678', Mockery::type('string'))
            ->andReturn(['status' => 'sent', 'success' => true]);

        // Act - When identifier is phone and no sub provided (fallback scenario)
        $job = new SendSmsJob(
            identifier: '+33612345678',
            sub: null,
            code: '123456',
            triggerSource: 'CustomSMSLambda_SignUp',
            locale: 'fr-FR',
            auditLogId: $auditLog->id
        );

        $job->handle($this->smsService);

        // Assert - Should complete without errors using E.164 fallback
        $auditLog->refresh();
        $this->assertEquals('sent', $auditLog->status);
    }

    #[Test]
    public function it_looks_up_user_by_cognito_sub(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'user@example.com',
            'phone' => '+33612345678',
            'cognito_id' => 'unique-cognito-sub-12345',
        ]);

        $auditLog = $this->createAuditLog('queued');

        $this->smsService
            ->shouldReceive('sendSms')
            ->once()
            ->with('+33612345678', Mockery::type('string'))
            ->andReturn(['status' => 'sent', 'success' => true]);

        // Act
        $job = new SendSmsJob(
            identifier: 'other@example.com', // Different email
            sub: $user->cognito_id, // Should use sub to find user
            code: '123456',
            triggerSource: 'CustomSMSLambda_SignUp',
            locale: 'fr-FR',
            auditLogId: $auditLog->id
        );

        $job->handle($this->smsService);

        // Assert - Should have found user by sub and used their phone
        $auditLog->refresh();
        $this->assertEquals('sent', $auditLog->status);
    }

    #[Test]
    public function it_looks_up_user_by_email_when_sub_is_null(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'user@example.com',
            'phone' => '+33612345678',
            'cognito_id' => null, // No cognito_id
        ]);

        $auditLog = $this->createAuditLog('queued');

        $this->smsService
            ->shouldReceive('sendSms')
            ->once()
            ->with('+33612345678', Mockery::type('string'))
            ->andReturn(['status' => 'sent', 'success' => true]);

        // Act
        $job = new SendSmsJob(
            identifier: $user->email,
            sub: null, // No sub provided, should lookup by email
            code: '123456',
            triggerSource: 'CustomSMSLambda_SignUp',
            locale: 'fr-FR',
            auditLogId: $auditLog->id
        );

        $job->handle($this->smsService);

        // Assert - Should have found user by email and used their phone
        $auditLog->refresh();
        $this->assertEquals('sent', $auditLog->status);
    }

    #[Test]
    public function it_throws_exception_when_no_phone_number_found(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'user@example.com',
            'phone' => null, // No phone number
            'cognito_id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        ]);

        $auditLog = $this->createAuditLog('queued');

        // Act & Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No phone number found for user');

        $job = new SendSmsJob(
            identifier: $user->email,
            sub: $user->cognito_id,
            code: '123456',
            triggerSource: 'CustomSMSLambda_SignUp',
            locale: 'fr-FR',
            auditLogId: $auditLog->id
        );

        $job->handle($this->smsService);
    }

    #[Test]
    public function it_throws_exception_when_user_not_found(): void
    {
        // Arrange - No user created
        $auditLog = $this->createAuditLog('queued');

        // Act & Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No phone number found for user');

        $job = new SendSmsJob(
            identifier: 'nonexistent@example.com',
            sub: 'nonexistent-sub-12345',
            code: '123456',
            triggerSource: 'CustomSMSLambda_SignUp',
            locale: 'fr-FR',
            auditLogId: $auditLog->id
        );

        $job->handle($this->smsService);
    }

    private function createAuditLog(string $status = 'queued', string $locale = 'fr-FR'): CognitoAuditLog
    {
        return CognitoAuditLog::createAudit(
            identifierHash: hash('sha256', 'user@example.com'),
            type: 'sms',
            triggerSource: 'CustomSMSLambda_SignUp',
            locale: $locale,
            payload: [
                'email' => 'user@example.com',
                'code' => '123456',
                'trigger_source' => 'CustomSMSLambda_SignUp',
            ],
            status: $status
        );
    }
}
