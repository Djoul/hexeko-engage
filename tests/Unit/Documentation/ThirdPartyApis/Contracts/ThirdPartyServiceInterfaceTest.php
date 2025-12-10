<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation\ThirdPartyApis\Contracts;

use App\Documentation\ThirdPartyApis\Contracts\ThirdPartyServiceInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('third-party-api-doc')]
class ThirdPartyServiceInterfaceTest extends TestCase
{
    private TestableService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TestableService;
    }

    #[Test]
    public function it_implements_third_party_service_interface(): void
    {
        $this->assertInstanceOf(ThirdPartyServiceInterface::class, $this->service);
    }

    #[Test]
    public function it_provides_provider_name(): void
    {
        $name = $this->service->getProviderName();

        $this->assertIsString($name);
        $this->assertNotEmpty($name);
        $this->assertEquals('testable', $name);
    }

    #[Test]
    public function it_provides_api_version(): void
    {
        $version = $this->service->getApiVersion();

        $this->assertIsString($version);
        $this->assertNotEmpty($version);
        $this->assertEquals('v1', $version);
    }

    #[Test]
    public function it_provides_health_check(): void
    {
        $isHealthy = $this->service->isHealthy();

        $this->assertIsBool($isHealthy);
        $this->assertTrue($isHealthy);
    }
}

/**
 * Test implementation of ThirdPartyServiceInterface
 */
class TestableService implements ThirdPartyServiceInterface
{
    public function getProviderName(): string
    {
        return 'testable';
    }

    public function getApiVersion(): string
    {
        return 'v1';
    }

    public function isHealthy(): bool
    {
        return true;
    }
}
