<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Wellbeing\WellWo\Tests\Unit;

use App\Integrations\Wellbeing\WellWo\Services\WellWoAuthService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WellWoAuthServiceTest extends TestCase
{
    private WellWoAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WellWoAuthService;
    }

    #[Test]
    public function it_returns_auth_token_as_array(): void
    {
        // Act
        $tokenData = $this->service->getAuthToken();

        // Assert
        $this->assertIsArray($tokenData);
        $this->assertArrayHasKey('token', $tokenData);
        $this->assertEquals('stub-token', $tokenData['token']);
    }

    #[Test]
    public function it_returns_auth_headers_with_bearer_token(): void
    {
        // Act
        $headers = $this->service->getAuthHeaders();

        // Assert
        $this->assertIsArray($headers);
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals('Bearer stub-token', $headers['Authorization']);
    }

    #[Test]
    public function it_validates_non_empty_token(): void
    {
        // Arrange
        $validToken = 'valid-token-123';

        // Act
        $isValid = $this->service->isTokenValid($validToken);

        // Assert
        $this->assertTrue($isValid);
    }

    #[Test]
    public function it_returns_false_for_empty_token(): void
    {
        // Act
        $isValid = $this->service->isTokenValid('');

        // Assert
        $this->assertFalse($isValid);
    }
}
