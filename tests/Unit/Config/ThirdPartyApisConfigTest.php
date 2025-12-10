<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('third-party-api-doc')]
class ThirdPartyApisConfigTest extends TestCase
{
    #[Test]
    public function it_has_third_party_apis_configuration(): void
    {
        $config = config('third-party-apis');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('log_calls', $config);
        $this->assertArrayHasKey('save_responses', $config);
        $this->assertArrayHasKey('providers', $config);
    }

    #[Test]
    public function it_has_global_configuration_options(): void
    {
        // Set config values for testing
        config(['third-party-apis.log_calls' => true]);
        config(['third-party-apis.save_responses' => false]);

        $this->assertNotNull(config('third-party-apis.log_calls'));
        $this->assertNotNull(config('third-party-apis.save_responses'));
        $this->assertIsBool(config('third-party-apis.log_calls'));
        $this->assertIsBool(config('third-party-apis.save_responses'));
    }

    #[Test]
    public function it_has_amilon_provider_configuration(): void
    {
        $amilonConfig = config('third-party-apis.providers.amilon');

        $this->assertIsArray($amilonConfig);
        $this->assertArrayHasKey('base_url', $amilonConfig);
        $this->assertArrayHasKey('client_id', $amilonConfig);
        $this->assertArrayHasKey('client_secret', $amilonConfig);
        $this->assertArrayHasKey('timeout', $amilonConfig);
        $this->assertArrayHasKey('retry', $amilonConfig);

        $this->assertIsArray($amilonConfig['retry']);
        $this->assertArrayHasKey('times', $amilonConfig['retry']);
        $this->assertArrayHasKey('sleep', $amilonConfig['retry']);
    }

    #[Test]
    public function it_uses_environment_variables_for_sensitive_data(): void
    {
        // Set test environment variables
        putenv('AMILON_CLIENT_ID=test-client-id');
        putenv('AMILON_CLIENT_SECRET=test-client-secret');
        putenv('AMILON_API_URL=https://test-api.amilon.com');

        // Reload config to pick up env changes
        config()->set('third-party-apis', require config_path('third-party-apis.php'));

        $amilonConfig = config('third-party-apis.providers.amilon');

        $this->assertEquals('test-client-id', $amilonConfig['client_id']);
        $this->assertEquals('test-client-secret', $amilonConfig['client_secret']);
        $this->assertEquals('https://test-api.amilon.com', $amilonConfig['base_url']);

        // Cleanup
        putenv('AMILON_CLIENT_ID');
        putenv('AMILON_CLIENT_SECRET');
        putenv('AMILON_API_URL');
    }

    #[Test]
    public function it_has_default_values_for_optional_settings(): void
    {
        $amilonConfig = config('third-party-apis.providers.amilon');

        $this->assertEquals(30, $amilonConfig['timeout']);
        $this->assertEquals(3, $amilonConfig['retry']['times']);
        $this->assertEquals(100, $amilonConfig['retry']['sleep']);
    }
}
