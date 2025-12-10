<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ForceHttps;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('admin-panel')]
#[Group('core')]
class ForceHttpsTest extends TestCase
{
    private ForceHttps $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ForceHttps;
    }

    #[Test]
    public function it_forces_https_scheme_in_production_environment(): void
    {
        // Arrange
        $this->app['env'] = 'production';
        $request = Request::create('http://example.com/test', 'GET');
        $next = function ($request): Response {
            return new Response('OK');
        };

        // Act
        $this->middleware->handle($request, $next);

        // Assert
        $generatedUrl = url('/test');
        $this->assertStringStartsWith('https://', $generatedUrl);
    }

    #[Test]
    public function it_forces_https_scheme_in_staging_environment(): void
    {
        // Arrange
        $this->app['env'] = 'staging';
        $request = Request::create('http://example.com/test', 'GET');
        $next = function ($request): Response {
            return new Response('OK');
        };

        // Act
        $this->middleware->handle($request, $next);

        // Assert
        $generatedUrl = url('/test');
        $this->assertStringStartsWith('https://', $generatedUrl);
    }

    #[Test]
    public function it_forces_https_scheme_in_dev_environment(): void
    {
        // Arrange
        $this->app['env'] = 'dev';
        $request = Request::create('http://example.com/test', 'GET');
        $next = function ($request): Response {
            return new Response('OK');
        };

        // Act
        $this->middleware->handle($request, $next);

        // Assert
        $generatedUrl = url('/test');
        $this->assertStringStartsWith('https://', $generatedUrl);
    }

    #[Test]
    public function it_does_not_force_https_in_local_environment(): void
    {
        // Arrange
        $this->app['env'] = 'local';
        URL::forceScheme('http'); // Reset to HTTP

        $request = Request::create('http://example.com/test', 'GET');
        $next = function ($request): Response {
            return new Response('OK');
        };

        // Act
        $this->middleware->handle($request, $next);

        // Assert
        $generatedUrl = url('/test');
        $this->assertStringStartsWith('http://', $generatedUrl);
    }

    #[Test]
    public function it_does_not_force_https_in_testing_environment(): void
    {
        // Arrange
        $this->app['env'] = 'testing';
        URL::forceScheme('http'); // Reset to HTTP

        $request = Request::create('http://example.com/test', 'GET');
        $next = function ($request): Response {
            return new Response('OK');
        };

        // Act
        $this->middleware->handle($request, $next);

        // Assert
        $generatedUrl = url('/test');
        $this->assertStringStartsWith('http://', $generatedUrl);
    }
}
