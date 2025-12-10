<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\HmacAuthMiddleware;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('cognito')]
#[Group('security')]
#[Group('hmac')]
class HmacAuthMiddlewareTest extends TestCase
{
    private HmacAuthMiddleware $middleware;

    private string $webhookSecret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->webhookSecret = 'test-webhook-secret-key-12345';
        config(['services.cognito.webhook_secret' => $this->webhookSecret]);
        config(['services.cognito.hmac_strict_mode' => true]);
        $this->middleware = new HmacAuthMiddleware;
    }

    #[Test]
    public function it_rejects_request_without_signature_header(): void
    {
        // Arrange
        $request = Request::create('/api/v1/cognito-notifications/send-sms', 'POST', [
            'email' => 'user@example.com',
        ]);

        // Act
        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK'));

        // Assert
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertStringContainsString('Missing HMAC signature', $response->getContent());
    }

    #[Test]
    public function it_rejects_request_without_timestamp_header(): void
    {
        // Arrange
        $request = Request::create('/api/v1/cognito-notifications/send-sms', 'POST', [
            'email' => 'user@example.com',
        ]);
        $request->headers->set('X-Cognito-Signature', 'some-signature');

        // Act
        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK'));

        // Assert
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertStringContainsString('Missing timestamp', $response->getContent());
    }

    #[Test]
    public function it_rejects_expired_timestamp(): void
    {
        // Arrange - Timestamp 10 minutes ago (expired, max 5 minutes)
        $expiredTimestamp = time() - 600;
        $request = Request::create('/api/v1/cognito-notifications/send-sms', 'POST', [
            'email' => 'user@example.com',
        ]);
        $request->headers->set('X-Cognito-Timestamp', (string) $expiredTimestamp);
        $request->headers->set('X-Cognito-Signature', 'any-signature');

        // Act
        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK'));

        // Assert
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertStringContainsString('Timestamp expired', $response->getContent());
    }

    #[Test]
    public function it_computes_valid_signature(): void
    {
        // Arrange
        $timestamp = time();
        $payload = json_encode(['email' => 'user@example.com', 'code' => '123456']);
        $expectedSignature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

        $request = Request::create(
            '/api/v1/cognito-notifications/send-sms',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );
        $request->headers->set('X-Cognito-Timestamp', (string) $timestamp);
        $request->headers->set('X-Cognito-Signature', $expectedSignature);

        // Act
        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK', 200));

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    #[Test]
    public function it_rejects_invalid_signature_in_strict_mode(): void
    {
        // Arrange
        config(['services.cognito.hmac_strict_mode' => true]);
        $timestamp = time();
        $payload = json_encode(['email' => 'user@example.com']);
        $invalidSignature = 'invalid-signature-hash';

        $request = Request::create(
            '/api/v1/cognito-notifications/send-sms',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );
        $request->headers->set('X-Cognito-Timestamp', (string) $timestamp);
        $request->headers->set('X-Cognito-Signature', $invalidSignature);

        // Act
        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK'));

        // Assert
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertStringContainsString('Invalid signature', $response->getContent());
    }

    #[Test]
    public function it_allows_invalid_signature_when_strict_mode_disabled(): void
    {
        // Arrange - Non-strict mode bypasses HMAC validation (IP whitelist bypass)
        config(['services.cognito.hmac_strict_mode' => false]);
        $timestamp = time();
        $payload = json_encode(['email' => 'user@example.com']);
        $invalidSignature = 'completely-wrong-signature';

        $request = Request::create(
            '/api/v1/cognito-notifications/send-sms',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );
        $request->headers->set('X-Cognito-Timestamp', (string) $timestamp);
        $request->headers->set('X-Cognito-Signature', $invalidSignature);

        // Act
        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK'));

        // Assert - Request should be allowed through (bypass)
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    #[Test]
    public function it_allows_valid_signature_with_empty_body(): void
    {
        // Arrange - Empty body should also work
        $timestamp = time();
        $payload = '';
        $expectedSignature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

        $request = Request::create(
            '/api/v1/cognito-notifications/send-sms',
            'POST',
            [],
            [],
            [],
            [],
            $payload
        );
        $request->headers->set('X-Cognito-Timestamp', (string) $timestamp);
        $request->headers->set('X-Cognito-Signature', $expectedSignature);

        // Act
        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK', 200));

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_validates_timestamp_is_not_in_future(): void
    {
        // Arrange - Timestamp 10 minutes in the future (invalid)
        $futureTimestamp = time() + 600;
        $request = Request::create('/api/v1/cognito-notifications/send-sms', 'POST', [
            'email' => 'user@example.com',
        ]);
        $request->headers->set('X-Cognito-Timestamp', (string) $futureTimestamp);
        $request->headers->set('X-Cognito-Signature', 'any-signature');

        // Act
        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK'));

        // Assert
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertStringContainsString('Invalid timestamp', $response->getContent());
    }

    #[Test]
    public function it_is_case_sensitive_for_signature(): void
    {
        // Arrange
        $timestamp = time();
        $payload = json_encode(['email' => 'user@example.com']);
        $validSignature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);
        $uppercaseSignature = strtoupper($validSignature);

        $request = Request::create(
            '/api/v1/cognito-notifications/send-sms',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );
        $request->headers->set('X-Cognito-Timestamp', (string) $timestamp);
        $request->headers->set('X-Cognito-Signature', $uppercaseSignature);

        // Act
        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK'));

        // Assert - Should fail because hash_hmac returns lowercase
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    #[Test]
    public function it_uses_webhook_secret_from_config(): void
    {
        // Arrange - Different secret should fail
        $differentSecret = 'different-secret-key';
        $timestamp = time();
        $payload = json_encode(['email' => 'user@example.com']);
        $signatureWithDifferentSecret = hash_hmac('sha256', $timestamp.$payload, $differentSecret);

        $request = Request::create(
            '/api/v1/cognito-notifications/send-sms',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );
        $request->headers->set('X-Cognito-Timestamp', (string) $timestamp);
        $request->headers->set('X-Cognito-Signature', $signatureWithDifferentSecret);

        // Act
        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK'));

        // Assert
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_json_payload_correctly(): void
    {
        // Arrange
        $timestamp = time();
        $payload = json_encode([
            'email' => 'user@example.com',
            'code' => '123456',
            'type' => 'sms',
        ]);
        $validSignature = hash_hmac('sha256', $timestamp.$payload, $this->webhookSecret);

        $request = Request::create(
            '/api/v1/cognito-notifications/send-sms',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );
        $request->headers->set('X-Cognito-Timestamp', (string) $timestamp);
        $request->headers->set('X-Cognito-Signature', $validSignature);

        // Act
        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK', 200));

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_rejects_if_webhook_secret_not_configured(): void
    {
        // Arrange
        config(['services.cognito.webhook_secret' => null]);
        $timestamp = time();
        $request = Request::create('/api/v1/cognito-notifications/send-sms', 'POST');
        $request->headers->set('X-Cognito-Timestamp', (string) $timestamp);
        $request->headers->set('X-Cognito-Signature', 'any-signature');

        // Act
        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK'));

        // Assert
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertStringContainsString('Webhook secret not configured', $response->getContent());
    }
}
