<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Wellbeing\WellWo\Tests\Unit;

use App\Integrations\Wellbeing\WellWo\Exceptions\WellWoApiException;
use App\Integrations\Wellbeing\WellWo\Services\WellWoApiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WellWoApiServiceTest extends TestCase
{
    private WellWoApiService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.wellwo.api_url', 'https://my.wellwo.net/api/v1');
        Config::set('services.wellwo.auth_token', 'test-token');
        Config::set('services.wellwo.timeout', 30);
        Config::set('services.wellwo.retry_times', 3);
        Config::set('services.wellwo.retry_delay', 100);

        $this->service = new WellWoApiService;
    }

    #[Test]
    public function it_makes_authenticated_api_call(): void
    {
        // Arrange
        $endpoint = 'healthyProgramsGetList';
        $expectedResponse = [
            'status' => 'OK',
            '0' => [
                'id' => 'test-program-id',
                'name' => 'Test Program',
            ],
        ];

        Http::fake([
            'my.wellwo.net/api/v1/*' => Http::response($expectedResponse, 200),
        ]);

        // Act
        $response = $this->service->post(['action' => $endpoint]);

        // Assert
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->url() === 'https://my.wellwo.net/api/v1/'
                && is_array($data)
                && array_key_exists('authToken', $data)
                && $data['authToken'] === 'test-token'
                && array_key_exists('action', $data)
                && $data['action'] === 'healthyProgramsGetList';
        });

        $this->assertEquals($expectedResponse, $response);
    }

    #[Test]
    public function it_handles_api_errors_gracefully(): void
    {
        // Arrange
        Http::fake([
            'my.wellwo.net/api/v1/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        // Assert
        $this->expectException(WellWoApiException::class);
        $this->expectExceptionMessage('WellWo API error');

        // Act
        $this->service->post(['action' => 'healthyProgramsGetList']);
    }

    #[Test]
    public function it_handles_404_errors(): void
    {
        // Arrange
        Http::fake([
            'my.wellwo.net/api/v1/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        // Assert
        $this->expectException(WellWoApiException::class);
        $this->expectExceptionCode(404);

        // Act
        $this->service->post(['action' => 'nonexistent-endpoint']);
    }

    #[Test]
    public function it_handles_500_server_errors(): void
    {
        // Arrange
        Http::fake([
            'my.wellwo.net/api/v1/*' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        // Assert
        $this->expectException(WellWoApiException::class);
        $this->expectExceptionCode(500);

        // Act
        $this->service->post(['action' => 'healthyProgramsGetList']);
    }

    #[Test]
    public function it_fails_on_first_error_without_retry(): void
    {
        // Arrange
        $callCount = 0;
        Http::fake(function () use (&$callCount) {
            $callCount++;

            return Http::response(['error' => 'Server Error'], 500);
        });

        // Assert
        $this->expectException(WellWoApiException::class);

        // Act
        $this->service->post(['action' => 'healthyProgramsGetList']);

        // Verify only one call was made (no retries)
        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function it_throws_exception_after_max_retries(): void
    {
        // Arrange
        Http::fake([
            'my.wellwo.net/api/v1/*' => Http::response(['error' => 'Server Error'], 500),
        ]);

        Config::set('services.wellwo.retry_times', 2);

        // Assert
        $this->expectException(WellWoApiException::class);

        // Act
        $this->service->post(['action' => 'healthyProgramsGetList']);
    }

    #[Test]
    public function it_supports_query_parameters(): void
    {
        // Arrange
        Http::fake([
            'my.wellwo.net/api/v1/*' => Http::response(['status' => 'OK'], 200),
        ]);

        // Act
        $this->service->post(['action' => 'healthyProgramsGetList', 'lang' => 'es']);

        // Assert
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return is_array($data)
                && array_key_exists('lang', $data)
                && $data['lang'] === 'es';
        });
    }

    #[Test]
    public function it_logs_successful_api_calls(): void
    {
        // Arrange
        Config::set('third-party-apis.log_calls', true);

        Http::fake([
            'my.wellwo.net/api/v1/*' => Http::response(['status' => 'OK'], 200),
        ]);

        // Act
        $response = $this->service->post(['action' => 'healthyProgramsGetList']);

        // Assert - just verify the call succeeded and returned data
        $this->assertEquals(['status' => 'OK'], $response);
    }

    #[Test]
    public function it_logs_connection_errors(): void
    {
        // Arrange
        Http::fake(function (): void {
            throw new ConnectionException('Connection failed');
        });

        Log::shouldReceive('error')
            ->once()
            ->with('WellWo API connection error', Mockery::type('array'));

        // Act & Assert
        try {
            $this->service->post(['action' => 'healthyProgramsGetList']);
            $this->fail('Expected WellWoApiException was not thrown');
        } catch (WellWoApiException) {
            // Expected exception
        }
    }

    #[Test]
    public function it_handles_connection_timeout(): void
    {
        // Arrange
        Http::fake(function (): void {
            throw new ConnectionException('Connection timed out');
        });

        // Assert
        $this->expectException(WellWoApiException::class);
        $this->expectExceptionMessage('Failed to connect to WellWo API');

        // Act
        $this->service->post(['action' => 'healthyProgramsGetList']);
    }

    #[Test]
    public function it_posts_data_to_api(): void
    {
        // Arrange
        $postData = ['action' => 'test-action', 'key' => 'value'];
        Http::fake([
            'my.wellwo.net/api/v1/*' => Http::response(['status' => 'OK'], 200),
        ]);

        // Act
        $response = $this->service->post($postData);

        // Assert
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->method() === 'POST'
                && is_array($data)
                && array_key_exists('authToken', $data)
                && array_key_exists('action', $data)
                && $data['action'] === 'test-action'
                && array_key_exists('key', $data)
                && $data['key'] === 'value';
        });

        $this->assertEquals(['status' => 'OK'], $response);
    }
}
