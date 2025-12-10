<?php

namespace Tests\Unit\Services\Apideck;

use App\Services\Apideck\ApideckService;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('apideck')]
class ApideckServiceConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.apideck.base_url', 'https://unify.apideck.com');
        Config::set('services.apideck.key', 'test-api-key');
        Config::set('services.apideck.app_id', 'test-app-id');
        Config::set('services.apideck.consumer_id', 'test-consumer-id');
    }

    // Note: service_id tests removed because ApideckService no longer sends
    // x-apideck-service-id header. Apideck automatically detects the connected
    // service based on the consumer's active Vault connection.

    #[Test]
    public function it_throws_exception_when_base_url_is_not_string(): void
    {
        Config::set('services.apideck.base_url', null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid base_url');

        new ApideckService;
    }

    #[Test]
    public function it_throws_exception_when_api_key_is_not_string(): void
    {
        Config::set('services.apideck.key', null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid apiKey');

        new ApideckService;
    }

    #[Test]
    public function it_throws_exception_when_app_id_is_not_string(): void
    {
        Config::set('services.apideck.app_id', null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid appId');

        new ApideckService;
    }

    #[Test]
    public function it_throws_exception_when_consumer_id_is_not_string(): void
    {
        Config::set('services.apideck.consumer_id', null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No financerId provided and no default consumer_id configured');

        $service = new ApideckService;
        // Trigger the initialization which will call getConsumerId
        $service->initializeConsumerId();
    }
}
