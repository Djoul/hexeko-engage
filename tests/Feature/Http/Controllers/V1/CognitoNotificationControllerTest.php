<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1;

use App\Jobs\Cognito\SendAuthEmailJob;
use App\Jobs\Cognito\SendSmsJob;
use App\Models\CognitoAuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('cognito')]
#[Group('controller')]
#[Group('api')]
class CognitoNotificationControllerTest extends TestCase
{
    use DatabaseTransactions;

    private string $webhookSecret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->webhookSecret = 'test-webhook-secret-key-12345';
        config(['services.cognito.webhook_secret' => $this->webhookSecret]);
        config(['services.cognito.hmac_strict_mode' => true]);

        Queue::fake();
    }

    #[Test]
    public function it_queues_sms_successfully(): void
    {
        // Arrange
        $timestamp = time();
        $payload = json_encode([
            'email' => 'user@example.com',
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
        ]);
        $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

        // Act
        $response = $this->postJson('/api/v1/cognito-notifications/send-sms', [
            'email' => 'user@example.com',
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
        ], [
            'X-Cognito-Signature' => $signature,
            'X-Cognito-Timestamp' => (string) $timestamp,
        ]);

        // Assert
        $response->assertStatus(202);
        $response->assertJsonStructure([
            'message',
            'queued',
        ]);
        $this->assertEquals('SMS queued successfully', $response->json('message'));
        $this->assertTrue($response->json('queued'));
    }

    #[Test]
    public function it_queues_email_successfully(): void
    {
        // Arrange
        $timestamp = time();
        $payload = json_encode([
            'email' => 'user@example.com',
            'code' => '123456',
            'trigger_source' => 'CustomEmailLambda_ForgotPassword',
        ]);
        $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

        // Act
        $response = $this->postJson('/api/v1/cognito-notifications/send-email', [
            'email' => 'user@example.com',
            'code' => '123456',
            'trigger_source' => 'CustomEmailLambda_ForgotPassword',
        ], [
            'X-Cognito-Signature' => $signature,
            'X-Cognito-Timestamp' => (string) $timestamp,
        ]);

        // Assert
        $response->assertStatus(202);
        $response->assertJsonStructure([
            'message',
            'queued',
        ]);
        $this->assertEquals('Email queued successfully', $response->json('message'));
        $this->assertTrue($response->json('queued'));
    }

    #[Test]
    public function it_creates_audit_log_with_encrypted_data(): void
    {
        // Arrange
        $timestamp = time();
        $payload = json_encode([
            'email' => 'user@example.com',
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
        ]);
        $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

        $initialCount = CognitoAuditLog::count();

        // Act
        $this->postJson('/api/v1/cognito-notifications/send-sms', [
            'email' => 'user@example.com',
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
        ], [
            'X-Cognito-Signature' => $signature,
            'X-Cognito-Timestamp' => (string) $timestamp,
        ]);

        // Assert
        $this->assertEquals($initialCount + 1, CognitoAuditLog::count());

        $auditLog = CognitoAuditLog::latest()->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals('sms', $auditLog->type);
        $this->assertEquals('CustomSMSLambda_SignUp', $auditLog->trigger_source);
        $this->assertEquals('queued', $auditLog->status);

        // Verify identifier is hashed
        $expectedHash = hash('sha256', strtolower(trim('user@example.com')));
        $this->assertEquals($expectedHash, $auditLog->identifier_hash);

        // Verify payload is encrypted (can be decrypted)
        $decryptedPayload = $auditLog->getDecryptedPayload();
        $this->assertIsArray($decryptedPayload);
        $this->assertEquals('user@example.com', $decryptedPayload['email']);
        $this->assertEquals('123456', $decryptedPayload['code']);
    }

    #[Test]
    public function it_validates_request_payload_for_sms(): void
    {
        // Arrange
        $timestamp = time();
        $payload = json_encode(['invalid' => 'data']);
        $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

        // Act - Missing required fields
        $response = $this->postJson('/api/v1/cognito-notifications/send-sms', [
            'invalid' => 'data',
        ], [
            'X-Cognito-Signature' => $signature,
            'X-Cognito-Timestamp' => (string) $timestamp,
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'trigger_source']);
    }

    #[Test]
    public function it_validates_request_payload_for_email(): void
    {
        // Arrange
        $timestamp = time();
        $payload = json_encode(['code' => '123']);
        $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

        // Act - Missing email and trigger_source
        $response = $this->postJson('/api/v1/cognito-notifications/send-email', [
            'code' => '123',
        ], [
            'X-Cognito-Signature' => $signature,
            'X-Cognito-Timestamp' => (string) $timestamp,
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'trigger_source']);
    }

    #[Test]
    public function it_validates_email_format(): void
    {
        // Arrange
        $timestamp = time();
        $payload = json_encode([
            'email' => 'invalid-email',
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
        ]);
        $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

        // Act
        $response = $this->postJson('/api/v1/cognito-notifications/send-sms', [
            'email' => 'invalid-email',
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
        ], [
            'X-Cognito-Signature' => $signature,
            'X-Cognito-Timestamp' => (string) $timestamp,
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function it_requires_hmac_signature(): void
    {
        // Act - No signature header
        $response = $this->postJson('/api/v1/cognito-notifications/send-sms', [
            'email' => 'user@example.com',
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
        ]);

        // Assert
        $response->assertStatus(401);
        $response->assertSee('Missing HMAC signature');
    }

    #[Test]
    public function it_throttles_sms_requests(): void
    {
        // Arrange
        $timestamp = time();
        $identifier = 'user@example.com';

        // Act - Send 10 requests (should all pass)
        for ($i = 0; $i < 10; $i++) {
            $payload = json_encode([
                'email' => $identifier,
                'code' => '123456',
                'trigger_source' => 'CustomSMSLambda_SignUp',
            ]);
            $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

            $response = $this->postJson('/api/v1/cognito-notifications/send-sms', [
                'email' => $identifier,
                'code' => '123456',
                'trigger_source' => 'CustomSMSLambda_SignUp',
            ], [
                'X-Cognito-Signature' => $signature,
                'X-Cognito-Timestamp' => (string) $timestamp,
            ]);

            $this->assertEquals(202, $response->status());
        }

        // Act - 11th request should be throttled
        $payload = json_encode([
            'email' => $identifier,
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
        ]);
        $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

        $response = $this->postJson('/api/v1/cognito-notifications/send-sms', [
            'email' => $identifier,
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
        ], [
            'X-Cognito-Signature' => $signature,
            'X-Cognito-Timestamp' => (string) $timestamp,
        ]);

        // Assert
        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
    }

    #[Test]
    public function it_throttles_email_requests(): void
    {
        // Arrange
        $timestamp = time();
        $identifier = 'user@example.com';

        // Act - Send 5 requests (should all pass)
        for ($i = 0; $i < 5; $i++) {
            $payload = json_encode([
                'email' => $identifier,
                'code' => '123456',
                'trigger_source' => 'CustomEmailLambda_ForgotPassword',
            ]);
            $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

            $response = $this->postJson('/api/v1/cognito-notifications/send-email', [
                'email' => $identifier,
                'code' => '123456',
                'trigger_source' => 'CustomEmailLambda_ForgotPassword',
            ], [
                'X-Cognito-Signature' => $signature,
                'X-Cognito-Timestamp' => (string) $timestamp,
            ]);

            $this->assertEquals(202, $response->status());
        }

        // Act - 6th request should be throttled
        $payload = json_encode([
            'email' => $identifier,
            'code' => '123456',
            'trigger_source' => 'CustomEmailLambda_ForgotPassword',
        ]);
        $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

        $response = $this->postJson('/api/v1/cognito-notifications/send-email', [
            'email' => $identifier,
            'code' => '123456',
            'trigger_source' => 'CustomEmailLambda_ForgotPassword',
        ], [
            'X-Cognito-Signature' => $signature,
            'X-Cognito-Timestamp' => (string) $timestamp,
        ]);

        // Assert
        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
    }

    #[Test]
    public function it_accepts_email_for_sms(): void
    {
        // Arrange - New architecture: email OR sub required, phone from DB
        $timestamp = time();
        $email = 'user@example.com';
        $payload = json_encode([
            'email' => $email,
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
        ]);
        $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

        // Act
        $response = $this->postJson('/api/v1/cognito-notifications/send-sms', [
            'email' => $email,
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
        ], [
            'X-Cognito-Signature' => $signature,
            'X-Cognito-Timestamp' => (string) $timestamp,
        ]);

        // Assert
        $response->assertStatus(202);

        // Verify audit log created with hashed email
        $auditLog = CognitoAuditLog::latest()->first();
        $expectedHash = hash('sha256', strtolower(trim($email)));
        $this->assertEquals($expectedHash, $auditLog->identifier_hash);
    }

    #[Test]
    public function it_stores_locale_in_audit_log(): void
    {
        // Arrange
        $timestamp = time();
        $payload = json_encode([
            'email' => 'user@example.com',
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
            'locale' => 'fr-FR',
        ]);
        $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

        // Act
        $this->postJson('/api/v1/cognito-notifications/send-sms', [
            'email' => 'user@example.com',
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
            'locale' => 'fr-FR',
        ], [
            'X-Cognito-Signature' => $signature,
            'X-Cognito-Timestamp' => (string) $timestamp,
        ]);

        // Assert
        $auditLog = CognitoAuditLog::latest()->first();
        $this->assertEquals('fr-FR', $auditLog->locale);
    }

    #[Test]
    public function it_defaults_to_french_locale_when_not_provided(): void
    {
        // Arrange
        $timestamp = time();
        $payload = json_encode([
            'email' => 'user@example.com',
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
        ]);
        $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

        // Act
        $this->postJson('/api/v1/cognito-notifications/send-sms', [
            'email' => 'user@example.com',
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
        ], [
            'X-Cognito-Signature' => $signature,
            'X-Cognito-Timestamp' => (string) $timestamp,
        ]);

        // Assert
        $auditLog = CognitoAuditLog::latest()->first();
        $this->assertEquals('fr-FR', $auditLog->locale);
    }

    #[Test]
    public function it_dispatches_sms_job_to_queue(): void
    {
        // Arrange
        $timestamp = time();
        $payload = json_encode([
            'email' => 'user@example.com',
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
            'locale' => 'en-GB',
        ]);
        $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

        // Act
        $this->postJson('/api/v1/cognito-notifications/send-sms', [
            'email' => 'user@example.com',
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
            'locale' => 'en-GB',
        ], [
            'X-Cognito-Signature' => $signature,
            'X-Cognito-Timestamp' => (string) $timestamp,
        ]);

        // Assert
        Queue::assertPushed(SendSmsJob::class, function (SendSmsJob $job): bool {
            return $job->identifier === 'user@example.com'
                && $job->code === '123456'
                && $job->triggerSource === 'CustomSMSLambda_SignUp'
                && $job->locale === 'en-GB';
        });
    }

    #[Test]
    public function it_dispatches_email_job_to_queue(): void
    {
        // Arrange
        $timestamp = time();
        $payload = json_encode([
            'email' => 'user@example.com',
            'code' => '654321',
            'trigger_source' => 'CustomEmailLambda_ForgotPassword',
            'locale' => 'pt-PT',
        ]);
        $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

        // Act
        $this->postJson('/api/v1/cognito-notifications/send-email', [
            'email' => 'user@example.com',
            'code' => '654321',
            'trigger_source' => 'CustomEmailLambda_ForgotPassword',
            'locale' => 'pt-PT',
        ], [
            'X-Cognito-Signature' => $signature,
            'X-Cognito-Timestamp' => (string) $timestamp,
        ]);

        // Assert
        Queue::assertPushed(SendAuthEmailJob::class, function (SendAuthEmailJob $job): bool {
            return $job->email === 'user@example.com'
                && $job->sub === null
                && $job->code === '654321'
                && $job->triggerSource === 'CustomEmailLambda_ForgotPassword'
                && $job->locale === 'pt-PT';
        });
    }

    #[Test]
    public function it_rejects_invalid_hmac_signature(): void
    {
        // Arrange
        $timestamp = time();

        // Act - Wrong signature
        $response = $this->postJson('/api/v1/cognito-notifications/send-sms', [
            'email' => 'user@example.com',
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
        ], [
            'X-Cognito-Signature' => 'invalid-signature',
            'X-Cognito-Timestamp' => (string) $timestamp,
        ]);

        // Assert
        $response->assertStatus(403); // Strict mode returns 403
        $response->assertSee('Invalid signature');
    }

    #[Test]
    public function it_rejects_expired_timestamp(): void
    {
        // Arrange
        $expiredTimestamp = time() - 400; // 400 seconds ago (> 5 min)
        $payload = json_encode([
            'email' => 'user@example.com',
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
        ]);
        $signature = hash_hmac('sha256', $expiredTimestamp.$payload, $this->webhookSecret);

        // Act
        $response = $this->postJson('/api/v1/cognito-notifications/send-sms', [
            'email' => 'user@example.com',
            'code' => '123456',
            'trigger_source' => 'CustomSMSLambda_SignUp',
        ], [
            'X-Cognito-Signature' => $signature,
            'X-Cognito-Timestamp' => (string) $expiredTimestamp,
        ]);

        // Assert
        $response->assertStatus(401);
        $response->assertSee('Timestamp expired');
    }

    #[Test]
    public function it_handles_all_supported_locales_for_sms(): void
    {
        $locales = ['fr-FR', 'en-GB', 'pt-PT', 'nl-NL', 'es-ES'];

        foreach ($locales as $locale) {
            // Arrange
            $timestamp = time();
            $email = "user-{$locale}@example.com";
            $payload = json_encode([
                'email' => $email,
                'code' => '123456',
                'trigger_source' => 'CustomSMSLambda_SignUp',
                'locale' => $locale,
            ]);
            $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

            // Act
            $response = $this->postJson('/api/v1/cognito-notifications/send-sms', [
                'email' => $email,
                'code' => '123456',
                'trigger_source' => 'CustomSMSLambda_SignUp',
                'locale' => $locale,
            ], [
                'X-Cognito-Signature' => $signature,
                'X-Cognito-Timestamp' => (string) $timestamp,
            ]);

            // Assert
            $response->assertStatus(202);
            $expectedHash = hash('sha256', strtolower(trim($email)));
            $auditLog = CognitoAuditLog::where('identifier_hash', $expectedHash)->latest()->first();
            $this->assertEquals($locale, $auditLog->locale, "Failed for locale: {$locale}");
        }
    }

    #[Test]
    public function it_handles_all_supported_locales_for_email(): void
    {
        $locales = ['fr-FR', 'en-GB', 'pt-PT', 'nl-NL', 'es-ES'];

        foreach ($locales as $locale) {
            // Arrange
            $timestamp = time();
            $email = "user-{$locale}@example.com";
            $payload = json_encode([
                'email' => $email,
                'code' => '654321',
                'trigger_source' => 'CustomEmailLambda_ForgotPassword',
                'locale' => $locale,
            ]);
            $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

            // Act
            $response = $this->postJson('/api/v1/cognito-notifications/send-email', [
                'email' => $email,
                'code' => '654321',
                'trigger_source' => 'CustomEmailLambda_ForgotPassword',
                'locale' => $locale,
            ], [
                'X-Cognito-Signature' => $signature,
                'X-Cognito-Timestamp' => (string) $timestamp,
            ]);

            // Assert
            $response->assertStatus(202);
            $expectedHash = hash('sha256', strtolower(trim($email)));
            $auditLog = CognitoAuditLog::where('identifier_hash', $expectedHash)->latest()->first();
            $this->assertEquals($locale, $auditLog->locale, "Failed for locale: {$locale}");
        }
    }

    #[Test]
    public function it_handles_all_sms_trigger_sources(): void
    {
        $triggerSources = [
            'CustomSMSLambda_SignUp',
            'CustomSMSLambda_ResendCode',
            'CustomSMSLambda_ForgotPassword',
            'CustomSMSLambda_AdminCreateUser',
            'CustomSMSLambda_Authentication',
        ];

        $index = 0;
        foreach ($triggerSources as $triggerSource) {
            // Arrange - Use unique email for each trigger to avoid hash collision
            $timestamp = time();
            $email = "user-trigger-{$index}@example.com";
            $payload = json_encode([
                'email' => $email,
                'code' => '123456',
                'trigger_source' => $triggerSource,
            ]);
            $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

            // Act
            $response = $this->postJson('/api/v1/cognito-notifications/send-sms', [
                'email' => $email,
                'code' => '123456',
                'trigger_source' => $triggerSource,
            ], [
                'X-Cognito-Signature' => $signature,
                'X-Cognito-Timestamp' => (string) $timestamp,
            ]);

            // Assert
            $response->assertStatus(202);
            $expectedHash = hash('sha256', strtolower(trim($email)));
            $auditLog = CognitoAuditLog::where('identifier_hash', $expectedHash)->latest()->first();
            $this->assertEquals($triggerSource, $auditLog->trigger_source, "Failed for trigger: {$triggerSource}");
            $index++;
        }
    }

    #[Test]
    public function it_handles_all_email_trigger_sources(): void
    {
        $triggerSources = [
            'CustomEmailLambda_ConfirmSignUp',
            'CustomEmailLambda_ResendCode',
            'CustomEmailLambda_ForgotPassword',
            'CustomEmailLambda_AdminCreateUser',
            'CustomEmailLambda_VerifyUserAttribute',
        ];

        $index = 0;
        foreach ($triggerSources as $triggerSource) {
            // Arrange - Use unique email for each trigger to avoid hash collision
            $timestamp = time();
            $email = "user-email-trigger-{$index}@example.com";
            $payload = json_encode([
                'email' => $email,
                'code' => '654321',
                'trigger_source' => $triggerSource,
            ]);
            $signature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

            // Act
            $response = $this->postJson('/api/v1/cognito-notifications/send-email', [
                'email' => $email,
                'code' => '654321',
                'trigger_source' => $triggerSource,
            ], [
                'X-Cognito-Signature' => $signature,
                'X-Cognito-Timestamp' => (string) $timestamp,
            ]);

            // Assert
            $response->assertStatus(202);
            $expectedHash = hash('sha256', strtolower(trim($email)));
            $auditLog = CognitoAuditLog::where('identifier_hash', $expectedHash)->latest()->first();
            $this->assertEquals($triggerSource, $auditLog->trigger_source, "Failed for trigger: {$triggerSource}");
            $index++;
        }
    }
}
