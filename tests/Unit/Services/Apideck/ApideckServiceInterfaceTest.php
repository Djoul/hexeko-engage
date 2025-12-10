<?php

namespace Tests\Unit\Services\Apideck;

use App\Documentation\ThirdPartyApis\Contracts\ThirdPartyServiceInterface;
use App\Services\Apideck\ApideckService;
use Http;
use Illuminate\Http\Client\ConnectionException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('apideck')]
class ApideckServiceInterfaceTest extends TestCase
{
    private ApideckService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ApideckService;
    }

    #[Test]
    public function it_implements_third_party_service_interface(): void
    {
        $this->assertInstanceOf(ThirdPartyServiceInterface::class, $this->service);
    }

    #[Test]
    public function it_returns_correct_provider_name(): void
    {
        $this->assertEquals('apideck', $this->service->getProviderName());
    }

    #[Test]
    public function it_returns_correct_api_version(): void
    {
        $this->assertEquals('v1', $this->service->getApiVersion());
    }

    #[Test]
    public function it_checks_health_status_correctly(): void
    {
        // Mock successful response
        Http::fake([
            '*vault/connections*' => Http::response([
                'status_code' => 200,
                'status' => 'OK',
                'data' => [],
            ], 200),
        ]);

        $this->assertTrue($this->service->isHealthy());
    }

    #[Test]
    public function it_returns_false_when_service_is_unhealthy(): void
    {
        // Mock failed response
        Http::fake([
            '*vault/connections*' => Http::response([
                'error' => 'Service Unavailable',
            ], 503),
        ]);

        $this->assertFalse($this->service->isHealthy());
    }

    #[Test]
    public function it_returns_false_when_service_times_out(): void
    {
        // Mock timeout by throwing an exception
        Http::fake(function (): void {
            throw new ConnectionException('Connection timed out');
        });

        $this->assertFalse($this->service->isHealthy());
    }

    #[Test]
    public function it_uses_logs_api_calls_trait(): void
    {
        // Check that the service uses the LogsApiCalls trait
        $traits = class_uses($this->service);

        $this->assertContains(
            'App\Documentation\ThirdPartyApis\Concerns\LogsApiCalls',
            $traits,
            'ApideckService should use LogsApiCalls trait'
        );
    }

    #[Test]
    public function it_has_required_methods_from_interface(): void
    {
        // Verify all interface methods are implemented
        $this->assertTrue(method_exists($this->service, 'getProviderName'));
        $this->assertTrue(method_exists($this->service, 'getApiVersion'));
        $this->assertTrue(method_exists($this->service, 'isHealthy'));
    }
}
