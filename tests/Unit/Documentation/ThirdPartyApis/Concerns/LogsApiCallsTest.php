<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation\ThirdPartyApis\Concerns;

use App\Documentation\ThirdPartyApis\Concerns\LogsApiCalls;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('third-party-api-doc')]
class LogsApiCallsTest extends TestCase
{
    private int $nbItems = 59;

    private TestableApiLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new TestableApiLogger;

        config(['third-party-apis.log_calls' => false]);
        config(['third-party-apis.save_responses' => false]);
    }

    #[Test]
    public function it_logs_api_calls_when_enabled(): void
    {
        config(['third-party-apis.log_calls' => true]);

        Log::shouldReceive('channel')
            ->once()
            ->with('third-party-apis')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('[POST] /test-endpoint **API Call**', Mockery::type('array'));

        $this->logger->testLogApiCall('POST', '/test-endpoint', 200, ['success' => true]);
    }

    #[Test]
    public function it_does_not_log_when_disabled(): void
    {
        config(['third-party-apis.log_calls' => false]);

        Log::shouldReceive('channel')->never();
        Log::shouldReceive('info')->never();

        $this->logger->testLogApiCall('POST', '/test-endpoint', 200, ['success' => true]);
    }

    #[Test]
    public function it_saves_response_snapshots_when_enabled(): void
    {
        config(['third-party-apis.log_calls' => true]);
        config(['third-party-apis.save_responses' => true]);

        Storage::fake('local');

        Log::shouldReceive('channel')->once()->andReturnSelf();
        Log::shouldReceive('info')->once();

        $response = ['data' => ['id' => 123, 'name' => 'Test']];
        $this->logger->testLogApiCall('GET', '/users/123', 200, $response);

        // Check that a file was saved
        $files = Storage::disk('local')->files('api-responses/testable');
        $this->assertNotEmpty($files, 'Response snapshot should be saved');

        // Verify content
        $content = Storage::disk('local')->get($files[0]);
        $savedData = json_decode($content, true);
        $this->assertEquals($response, $savedData);
    }

    #[Test]
    public function it_limits_sample_response_in_logs(): void
    {
        config(['third-party-apis.log_calls' => true]);

        $largeResponse = array_fill(0, 10, ['data' => 'test']);

        Log::shouldReceive('channel')->once()->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                // Check that response_sample has maximum 3 items
                return count($context['response_sample']) <= $this->nbItems;
            });

        $this->logger->testLogApiCall('GET', '/large', 200, $largeResponse);
    }

    #[Test]
    public function it_includes_required_log_data(): void
    {
        config(['third-party-apis.log_calls' => true]);

        Log::shouldReceive('channel')->once()->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return $message === '[POST] /test **API Call**' &&
                    isset($context['provider']) &&
                    isset($context['method']) &&
                    isset($context['endpoint']) &&
                    isset($context['status']) &&
                    isset($context['timestamp']) &&
                    isset($context['response_sample']) &&
                    $context['provider'] === 'testable' &&
                    $context['method'] === 'POST' &&
                    $context['endpoint'] === '/test' &&
                    $context['status'] === 201;
            });

        $this->logger->testLogApiCall('POST', '/test', 201, ['created' => true]);
    }
}

/**
 * Test class using LogsApiCalls trait
 */
class TestableApiLogger
{
    use LogsApiCalls;

    public function getProviderName(): string
    {
        return 'testable';
    }

    public function testLogApiCall(string $method, string $endpoint, int $status, array $response): void
    {
        $this->logApiCall($method, $endpoint, $status, $response);
    }
}
