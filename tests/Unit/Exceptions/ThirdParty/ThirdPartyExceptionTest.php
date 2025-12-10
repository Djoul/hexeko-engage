<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions\ThirdParty;

use App\Exceptions\ThirdParty\ThirdPartyException;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('third-party-api-doc')]
class ThirdPartyExceptionTest extends TestCase
{
    #[Test]
    public function it_stores_provider_information(): void
    {
        $exception = new ThirdPartyException(
            'stripe',
            'Payment failed',
            422,
            ['error' => 'insufficient_funds']
        );

        $this->assertEquals('stripe', $exception->getProvider());
        $this->assertEquals('Payment failed', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatus());
        $this->assertEquals(['error' => 'insufficient_funds'], $exception->getResponseBody());
    }

    #[Test]
    public function it_handles_null_response_body(): void
    {
        $exception = new ThirdPartyException(
            'amilon',
            'Connection timeout',
            0,
            null
        );

        $this->assertNull($exception->getResponseBody());
    }

    #[Test]
    public function it_chains_previous_exception(): void
    {
        $previous = new Exception('Original error');
        $exception = new ThirdPartyException(
            'sendgrid',
            'Email sending failed',
            500,
            null,
            $previous
        );

        $this->assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function it_renders_json_response(): void
    {
        $exception = new ThirdPartyException(
            'stripe',
            'Invalid API key',
            401,
            ['error' => 'invalid_api_key']
        );

        $response = $exception->render();

        $this->assertEquals(422, $response->status());

        $data = $response->getData(true);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('provider', $data);
        $this->assertEquals('stripe', $data['provider']);
    }

    #[Test]
    public function it_provides_user_friendly_message(): void
    {
        $exception = new ThirdPartyException(
            'amilon',
            'Technical error message',
            500
        );

        $response = $exception->render();
        $data = $response->getData(true);

        $this->assertNotEquals('Technical error message', $data['message']);
        $this->assertStringContainsString('service', $data['message']);
    }
}
