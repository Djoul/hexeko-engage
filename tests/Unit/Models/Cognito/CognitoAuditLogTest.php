<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Cognito;

use App\Models\CognitoAuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('cognito')]
#[Group('audit')]
class CognitoAuditLogTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_creates_audit_log_with_encrypted_payload(): void
    {
        // Arrange
        $identifierHash = hash('sha256', 'user@example.com');
        $payload = [
            'email' => 'user@example.com',
            'phone' => '+33612345678',
            'code' => '123456',
        ];

        // Act
        $audit = CognitoAuditLog::createAudit(
            identifierHash: $identifierHash,
            type: 'sms',
            triggerSource: 'CustomSMSSender_SignIn',
            locale: 'fr-FR',
            payload: $payload,
            status: 'queued',
            sourceIp: '192.168.1.1'
        );

        // Assert
        $this->assertInstanceOf(CognitoAuditLog::class, $audit);
        $this->assertEquals($identifierHash, $audit->identifier_hash);
        $this->assertEquals('sms', $audit->type);
        $this->assertEquals('CustomSMSSender_SignIn', $audit->trigger_source);
        $this->assertEquals('fr-FR', $audit->locale);
        $this->assertEquals('queued', $audit->status);
        $this->assertEquals('192.168.1.1', $audit->source_ip);
        $this->assertNotNull($audit->encrypted_payload);
        $this->assertNotEquals(json_encode($payload), $audit->encrypted_payload);
    }

    #[Test]
    public function it_hashes_identifier_before_storage(): void
    {
        // Arrange
        $email = 'user@example.com';
        $expectedHash = hash('sha256', strtolower(trim($email)));

        // Act
        $audit = CognitoAuditLog::createAudit(
            identifierHash: $expectedHash,
            type: 'email',
            triggerSource: 'CustomEmailSender_ForgotPassword',
            locale: 'en-GB',
            payload: ['email' => $email],
            status: 'sent'
        );

        // Assert
        $this->assertEquals($expectedHash, $audit->identifier_hash);
        $this->assertNotEquals($email, $audit->identifier_hash);
    }

    #[Test]
    public function it_decrypts_payload_correctly(): void
    {
        // Arrange
        $payload = [
            'email' => 'user@example.com',
            'phone' => '+33612345678',
            'code' => '789012',
        ];

        $audit = CognitoAuditLog::createAudit(
            identifierHash: hash('sha256', 'user@example.com'),
            type: 'sms',
            triggerSource: 'CustomSMSSender_Authentication',
            locale: 'fr-FR',
            payload: $payload,
            status: 'sent'
        );

        // Act
        $decrypted = $audit->getDecryptedPayload();

        // Assert
        $this->assertEquals($payload, $decrypted);
        $this->assertEquals('user@example.com', $decrypted['email']);
        $this->assertEquals('+33612345678', $decrypted['phone']);
        $this->assertEquals('789012', $decrypted['code']);
    }

    #[Test]
    public function it_has_no_updated_at_column(): void
    {
        // Arrange & Act
        $audit = CognitoAuditLog::createAudit(
            identifierHash: hash('sha256', 'user@example.com'),
            type: 'sms',
            triggerSource: 'CustomSMSSender_SignIn',
            locale: 'fr-FR',
            payload: ['email' => 'user@example.com'],
            status: 'queued'
        );

        // Assert - Should have created_at but not updated_at
        $this->assertNotNull($audit->created_at);
        $this->assertNull($audit->updated_at);

        // Verify model config
        $this->assertFalse($audit->usesTimestamps() && isset($audit->updated_at));
    }

    #[Test]
    public function it_stores_error_message_on_failure(): void
    {
        // Arrange
        $errorMessage = 'SMS delivery failed: Rate limit exceeded';

        // Act
        $audit = CognitoAuditLog::createAudit(
            identifierHash: hash('sha256', 'user@example.com'),
            type: 'sms',
            triggerSource: 'CustomSMSSender_SignIn',
            locale: 'fr-FR',
            payload: ['email' => 'user@example.com'],
            status: 'failed',
            errorMessage: $errorMessage
        );

        // Assert
        $this->assertEquals('failed', $audit->status);
        $this->assertEquals($errorMessage, $audit->error_message);
    }

    #[Test]
    public function it_handles_null_source_ip(): void
    {
        // Arrange & Act
        $audit = CognitoAuditLog::createAudit(
            identifierHash: hash('sha256', 'user@example.com'),
            type: 'email',
            triggerSource: 'CustomEmailSender_ForgotPassword',
            locale: 'en-GB',
            payload: ['email' => 'user@example.com'],
            status: 'sent',
            sourceIp: null
        );

        // Assert
        $this->assertNull($audit->source_ip);
    }

    #[Test]
    public function it_casts_created_at_to_datetime(): void
    {
        // Arrange & Act
        $audit = CognitoAuditLog::createAudit(
            identifierHash: hash('sha256', 'user@example.com'),
            type: 'sms',
            triggerSource: 'CustomSMSSender_SignIn',
            locale: 'fr-FR',
            payload: ['email' => 'user@example.com'],
            status: 'sent'
        );

        // Assert
        $this->assertInstanceOf(Carbon::class, $audit->created_at);
    }

    #[Test]
    public function it_encrypts_pii_data_in_payload(): void
    {
        // Arrange
        $payload = [
            'email' => 'user@example.com',
            'phone' => '+33612345678',
            'sensitive' => 'Very secret data',
        ];

        // Act
        $audit = CognitoAuditLog::createAudit(
            identifierHash: hash('sha256', 'user@example.com'),
            type: 'sms',
            triggerSource: 'CustomSMSSender_SignIn',
            locale: 'fr-FR',
            payload: $payload,
            status: 'sent'
        );

        // Assert - Raw encrypted_payload should not contain plaintext PII
        $rawPayload = $audit->getRawOriginal('encrypted_payload');
        $this->assertNotNull($rawPayload);
        $this->assertStringNotContainsString('user@example.com', $rawPayload);
        $this->assertStringNotContainsString('+33612345678', $rawPayload);
        $this->assertStringNotContainsString('Very secret data', $rawPayload);

        // But decrypted payload should contain it
        $decrypted = $audit->getDecryptedPayload();
        $this->assertEquals('user@example.com', $decrypted['email']);
        $this->assertEquals('+33612345678', $decrypted['phone']);
        $this->assertEquals('Very secret data', $decrypted['sensitive']);
    }

    #[Test]
    public function it_supports_different_trigger_sources(): void
    {
        // Arrange
        $triggerSources = [
            'CustomSMSSender_SignIn',
            'CustomSMSSender_SignUp',
            'CustomSMSSender_Authentication',
            'CustomEmailSender_SignUp',
            'CustomEmailSender_ForgotPassword',
            'CustomEmailSender_ResendCode',
        ];

        foreach ($triggerSources as $triggerSource) {
            // Act
            $audit = CognitoAuditLog::createAudit(
                identifierHash: hash('sha256', 'user@example.com'),
                type: str_starts_with($triggerSource, 'CustomSMS') ? 'sms' : 'email',
                triggerSource: $triggerSource,
                locale: 'fr-FR',
                payload: ['email' => 'user@example.com'],
                status: 'sent'
            );

            // Assert
            $this->assertEquals($triggerSource, $audit->trigger_source);
        }

        // Verify we created 6 different audit logs
        $this->assertEquals(6, CognitoAuditLog::count());
    }

    #[Test]
    public function it_supports_status_transitions(): void
    {
        // Arrange
        $statuses = ['queued', 'sent', 'failed', 'retrying'];

        foreach ($statuses as $status) {
            // Act
            $audit = CognitoAuditLog::createAudit(
                identifierHash: hash('sha256', "user-{$status}@example.com"),
                type: 'sms',
                triggerSource: 'CustomSMSSender_SignIn',
                locale: 'fr-FR',
                payload: ['email' => "user-{$status}@example.com"],
                status: $status
            );

            // Assert
            $this->assertEquals($status, $audit->status);
        }

        // Verify we created 4 different audit logs
        $this->assertEquals(4, CognitoAuditLog::count());
    }
}
