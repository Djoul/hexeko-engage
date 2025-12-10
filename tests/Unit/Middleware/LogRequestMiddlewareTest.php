<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\LogRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('logging')]
class LogRequestMiddlewareTest extends TestCase
{
    private LogRequest $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new LogRequest;
    }

    #[Test]
    public function it_skips_logging_for_excluded_paths(): void
    {
        Log::shouldReceive('withContext')->never();
        Log::shouldReceive('info')->never();

        Config::set('logging.request_logging.enabled', true);
        Config::set('logging.request_logging.excluded_paths', ['health-check']);

        $request = Request::create('/health-check', 'GET');

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['status' => 'ok']);
        });

        $this->assertEquals(200, $response->status());
    }

    #[Test]
    public function it_skips_logging_for_wildcard_excluded_paths(): void
    {
        Log::shouldReceive('withContext')->never();
        Log::shouldReceive('info')->never();

        Config::set('logging.request_logging.enabled', true);
        Config::set('logging.request_logging.excluded_paths', ['livewire*', 'log-viewer*']);

        // Test livewire message endpoint
        $request = Request::create('/livewire/message/admin-panel.manager.translation.manager', 'POST');

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['status' => 'ok']);
        });

        $this->assertEquals(200, $response->status());

        // Test log-viewer API endpoint
        $request2 = Request::create('/log-viewer/api/logs', 'GET');

        $response2 = $this->middleware->handle($request2, function ($req) {
            return response()->json(['status' => 'ok']);
        });

        $this->assertEquals(200, $response2->status());
    }

    #[Test]
    public function it_skips_logging_for_nested_wildcard_paths(): void
    {
        Log::shouldReceive('withContext')->never();
        Log::shouldReceive('info')->never();

        Config::set('logging.request_logging.enabled', true);
        Config::set('logging.request_logging.excluded_paths', ['api/v1/health*']);

        $request = Request::create('/api/v1/health/detailed', 'GET');

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['status' => 'ok']);
        });

        $this->assertEquals(200, $response->status());
    }

    #[Test]
    public function it_skips_logging_for_excluded_route_names(): void
    {
        Log::shouldReceive('withContext')->never();
        Log::shouldReceive('info')->never();

        Config::set('logging.request_logging.enabled', true);
        Config::set('logging.request_logging.excluded_route_names', ['metrics']);

        Route::get('/metrics', fn () => response()->json(['status' => 'ok']))->name('metrics');

        $request = Request::create('/metrics', 'GET');
        $request->setRouteResolver(function () use ($request) {
            return Route::getRoutes()->match($request);
        });

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['status' => 'ok']);
        });

        $this->assertEquals(200, $response->status());
    }

    #[Test]
    public function it_logs_requests_when_not_excluded(): void
    {
        Log::shouldReceive('withContext')->once();
        Log::shouldReceive('info')->twice(); // Start and end logs

        Config::set('logging.request_logging.enabled', true);
        Config::set('logging.request_logging.excluded_paths', []);
        Config::set('logging.request_logging.excluded_route_names', []);

        $request = Request::create('/api/v1/users', 'GET');

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['data' => []]);
        });

        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->headers->has('X-Request-ID'));
    }

    #[Test]
    public function it_skips_all_logging_when_disabled(): void
    {
        Log::shouldReceive('withContext')->never();
        Log::shouldReceive('info')->never();

        Config::set('logging.request_logging.enabled', false);

        $request = Request::create('/api/v1/users', 'GET');

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['data' => []]);
        });

        $this->assertEquals(200, $response->status());
    }

    #[Test]
    public function it_respects_channel_scoped_configuration_when_disabling_logging(): void
    {
        Log::shouldReceive('withContext')->never();
        Log::shouldReceive('info')->never();

        Config::set('logging.channels.request_logging.enabled', false);
        Config::set('logging.channels.request_logging.excluded_paths', ['*']);

        $request = Request::create('/api/v1/users', 'GET');

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['data' => []]);
        });

        $this->assertEquals(200, $response->status());
    }

    #[Test]
    public function it_uses_appropriate_log_levels_for_status_codes(): void
    {
        Config::set('logging.request_logging.enabled', true);
        Config::set('logging.request_logging.excluded_paths', []);

        // Info level for 2xx
        Log::shouldReceive('withContext')->once();
        Log::shouldReceive('info')->twice();

        $request = Request::create('/api/v1/test', 'GET');
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['data' => []], 200);
        });

        $this->assertEquals(200, $response->status());
    }

    #[Test]
    public function it_sanitizes_sensitive_data_in_request_body(): void
    {
        Config::set('logging.request_logging.enabled', true);
        Config::set('logging.request_logging.log_body', true);

        Log::shouldReceive('withContext')->once();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                // Verify password is excluded and api_key is redacted
                return ! isset($context['params']['body']['password'])
                    && $context['params']['body']['email'] === 'user@example.com'
                    && $context['params']['body']['api_key'] === '***REDACTED***';
            });
        Log::shouldReceive('info')->once(); // End log

        $request = Request::create('/api/v1/test', 'POST', [
            'email' => 'user@example.com',
            'password' => 'secret123',
            'api_key' => 'secret-api-key',
        ]);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['data' => []], 201);
        });

        $this->assertEquals(201, $response->status());
    }

    #[Test]
    public function it_handles_path_normalization_correctly(): void
    {
        Log::shouldReceive('withContext')->never();
        Log::shouldReceive('info')->never();

        Config::set('logging.request_logging.enabled', true);
        Config::set('logging.request_logging.excluded_paths', [
            '/health-check', // With leading slash
            'api/v1/health*', // Without leading slash + wildcard
            'livewire/*', // Without leading slash + wildcard with slash
        ]);

        // Test path with leading slash in config
        $request1 = Request::create('/health-check', 'GET');
        $response1 = $this->middleware->handle($request1, function ($req) {
            return response()->json(['status' => 'ok']);
        });
        $this->assertEquals(200, $response1->status());

        // Test wildcard path
        $request2 = Request::create('/api/v1/health/detailed', 'GET');
        $response2 = $this->middleware->handle($request2, function ($req) {
            return response()->json(['status' => 'ok']);
        });
        $this->assertEquals(200, $response2->status());

        // Test livewire path with slash
        $request3 = Request::create('/livewire/message/component', 'POST');
        $response3 = $this->middleware->handle($request3, function ($req) {
            return response()->json(['status' => 'ok']);
        });
        $this->assertEquals(200, $response3->status());
    }

    #[Test]
    public function it_uses_explicit_str_is_for_wildcard_matching(): void
    {
        Log::shouldReceive('withContext')->never();
        Log::shouldReceive('info')->never();

        Config::set('logging.request_logging.enabled', true);
        Config::set('logging.request_logging.excluded_paths', ['admin-panel/*']);

        // Test deep nested path
        $request = Request::create('/admin-panel/api/users/123/permissions', 'GET');

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['status' => 'ok']);
        });

        $this->assertEquals(200, $response->status());
    }
}
