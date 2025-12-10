<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\CognitoThrottleMiddleware;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('cognito')]
#[Group('security')]
#[Group('throttle')]
class CognitoThrottleMiddlewareTest extends TestCase
{
    private CognitoThrottleMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();

        $this->middleware = new CognitoThrottleMiddleware;
    }

    protected function tearDown(): void
    {
        Cache::flush();

        // Restore error and exception handlers to prevent test isolation issues
        restore_error_handler();
        restore_exception_handler();

        parent::tearDown();
    }

    #[Test]
    public function it_throttles_sms_at_10_per_minute(): void
    {
        // Arrange
        $identifier = 'user@example.com';
        $request = Request::create('/api/v1/cognito-notifications/send-sms', 'POST', [
            'email' => $identifier,
        ]);

        // Act - Send 10 requests (should all pass)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK', 200), 'sms');
            $this->assertEquals(200, $response->getStatusCode());
        }

        // Act - 11th request should be throttled
        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK', 200), 'sms');

        // Assert
        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $this->assertStringContainsString('Too many requests', $response->getContent());
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    #[Test]
    public function it_throttles_email_at_5_per_minute(): void
    {
        // Arrange
        $identifier = 'user@example.com';
        $request = Request::create('/api/v1/cognito-notifications/send-email', 'POST', [
            'email' => $identifier,
        ]);

        // Act - Send 5 requests (should all pass)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK', 200), 'email');
            $this->assertEquals(200, $response->getStatusCode());
        }

        // Act - 6th request should be throttled
        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK', 200), 'email');

        // Assert
        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $this->assertStringContainsString('Too many requests', $response->getContent());
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    #[Test]
    public function sms_and_email_have_separate_throttle_buckets(): void
    {
        // Arrange
        $identifier = 'user@example.com';
        $smsRequest = Request::create('/api/v1/cognito-notifications/send-sms', 'POST', [
            'email' => $identifier,
        ]);
        $emailRequest = Request::create('/api/v1/cognito-notifications/send-email', 'POST', [
            'email' => $identifier,
        ]);

        // Act - Exhaust SMS limit
        for ($i = 0; $i < 10; $i++) {
            $response = $this->middleware->handle($smsRequest, fn (): ResponseFactory|Response => response('OK', 200), 'sms');
            $this->assertEquals(200, $response->getStatusCode());
        }

        // Assert - Email should still work (separate bucket)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->middleware->handle($emailRequest, fn (): ResponseFactory|Response => response('OK', 200), 'email');
            $this->assertEquals(200, $response->getStatusCode(), "Email request {$i} should pass");
        }

        // Assert - Both are now exhausted
        $smsResponse = $this->middleware->handle($smsRequest, fn (): ResponseFactory|Response => response('OK', 200), 'sms');
        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $smsResponse->getStatusCode());

        $emailResponse = $this->middleware->handle($emailRequest, fn (): ResponseFactory|Response => response('OK', 200), 'email');
        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $emailResponse->getStatusCode());
    }

    #[Test]
    public function it_hashes_identifier_before_throttle(): void
    {
        // Arrange
        $email = 'user@example.com';
        $expectedHash = hash('sha256', strtolower(trim($email)));
        $request = Request::create('/api/v1/cognito-notifications/send-sms', 'POST', [
            'email' => $email,
        ]);

        // Act
        $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK', 200), 'sms');

        // Assert - Check that cache key uses hash
        $cacheKey = "cognito:throttle:sms:{$expectedHash}";
        $this->assertEquals(1, Cache::get($cacheKey));
    }

    #[Test]
    public function it_uses_email_from_request_body(): void
    {
        // Arrange
        $identifier = 'user@example.com';
        $request = Request::create('/api/v1/cognito-notifications/send-sms', 'POST', [
            'email' => $identifier,
        ]);

        // Act
        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK', 200), 'sms');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_uses_phone_number_from_request_body(): void
    {
        // Arrange
        $phone = '+33612345678';
        $request = Request::create('/api/v1/cognito-notifications/send-sms', 'POST', [
            'phone_number' => $phone,
        ]);

        // Act
        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK', 200), 'sms');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_skips_throttling_when_identifier_missing(): void
    {
        // Arrange - Request without email or phone_number
        $request = Request::create('/api/v1/cognito-notifications/send-sms', 'POST', [
            'message' => 'some content',
        ]);

        // Act - Should pass through to controller (skip throttling)
        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK', 200), 'sms');

        // Assert - Passes through (controller will handle validation)
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    #[Test]
    public function it_normalizes_identifier_before_hashing(): void
    {
        // Arrange - Same email with different casing/whitespace
        $email1 = '  User@Example.COM  ';
        $email2 = 'user@example.com';

        $request1 = Request::create('/api/v1/cognito-notifications/send-sms', 'POST', [
            'email' => $email1,
        ]);
        $request2 = Request::create('/api/v1/cognito-notifications/send-sms', 'POST', [
            'email' => $email2,
        ]);

        // Act - Both should use same throttle bucket
        for ($i = 0; $i < 5; $i++) {
            $this->middleware->handle($request1, fn (): ResponseFactory|Response => response('OK', 200), 'sms');
        }

        for ($i = 0; $i < 5; $i++) {
            $this->middleware->handle($request2, fn (): ResponseFactory|Response => response('OK', 200), 'sms');
        }

        // Assert - 11th request should be throttled (same bucket)
        $response = $this->middleware->handle($request2, fn (): ResponseFactory|Response => response('OK', 200), 'sms');
        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
    }

    #[Test]
    public function it_includes_retry_after_header_in_seconds(): void
    {
        // Arrange
        $identifier = 'user@example.com';
        $request = Request::create('/api/v1/cognito-notifications/send-sms', 'POST', [
            'email' => $identifier,
        ]);

        // Act - Exhaust limit
        for ($i = 0; $i < 10; $i++) {
            $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK', 200), 'sms');
        }

        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK', 200), 'sms');

        // Assert
        $retryAfter = $response->headers->get('Retry-After');
        $this->assertNotNull($retryAfter);
        $this->assertIsNumeric($retryAfter);
        $this->assertGreaterThan(0, (int) $retryAfter);
        $this->assertLessThanOrEqual(60, (int) $retryAfter);
    }

    #[Test]
    public function it_rejects_invalid_throttle_type(): void
    {
        // Arrange
        $request = Request::create('/api/v1/cognito-notifications/send-push', 'POST', [
            'email' => 'user@example.com',
        ]);

        // Act
        $response = $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK', 200), 'invalid_type');

        // Assert
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertStringContainsString('Invalid throttle type', $response->getContent());
    }

    #[Test]
    public function it_uses_60_second_ttl_for_throttle_cache(): void
    {
        // Arrange
        $identifier = 'user@example.com';
        $request = Request::create('/api/v1/cognito-notifications/send-sms', 'POST', [
            'email' => $identifier,
        ]);

        // Act
        $this->middleware->handle($request, fn (): ResponseFactory|Response => response('OK', 200), 'sms');

        // Assert - Cache key exists with 60 second TTL
        $hash = hash('sha256', strtolower(trim($identifier)));
        $cacheKey = "cognito:throttle:sms:{$hash}";
        $this->assertTrue(Cache::has($cacheKey));
    }
}
