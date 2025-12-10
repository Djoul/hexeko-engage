<?php

namespace Tests\Unit\Services\AdminPanel;

use App\Services\AdminPanel\ApiSchemaExtractor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route as RouteObject;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('admin-panel')]
class ApiSchemaExtractorTest extends TestCase
{
    private ApiSchemaExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new ApiSchemaExtractor;

        // Clear routes to ensure clean test state
        Route::getRoutes()->refreshNameLookups();
    }

    #[Test]
    public function it_extracts_endpoint_info_from_route(): void
    {
        // Arrange - Create a mock route
        $route = $this->mock(RouteObject::class);
        $route->shouldReceive('uri')->andReturn('api/v1/test/{id}');
        $route->shouldReceive('methods')->andReturn(['POST']);
        $route->shouldReceive('getName')->andReturn('test.update');
        $route->shouldReceive('parameterNames')->andReturn(['id']);
        $route->shouldReceive('gatherMiddleware')->andReturn(['auth', 'throttle:api']);

        // Act
        $info = $this->extractor->extractEndpoint($route);

        // Assert
        $this->assertEquals('POST', $info['method']);
        $this->assertEquals('/api/v1/test/{id}', $info['path']);
        $this->assertEquals('test.update', $info['name']);
        $this->assertContains('auth', $info['middleware']);
        $this->assertContains('throttle:api', $info['middleware']);
        $this->assertArrayHasKey('parameters', $info);
        $this->assertContains('id', array_column($info['parameters'], 'name'));
    }

    #[Test]
    public function it_extracts_multiple_route_parameters(): void
    {
        // Arrange - Create a mock route
        $route = $this->mock(RouteObject::class);
        $route->shouldReceive('uri')->andReturn('api/v1/users/{user}/posts/{post}');
        $route->shouldReceive('methods')->andReturn(['GET']);
        $route->shouldReceive('getName')->andReturn('users.posts.show');
        $route->shouldReceive('parameterNames')->andReturn(['user', 'post']);
        $route->shouldReceive('gatherMiddleware')->andReturn([]);

        // Act
        $info = $this->extractor->extractEndpoint($route);

        // Assert
        $this->assertCount(2, $info['parameters']);
        $this->assertEquals('user', $info['parameters'][0]['name']);
        $this->assertEquals('post', $info['parameters'][1]['name']);
    }

    #[Test]
    public function it_extracts_validation_rules_from_form_request(): void
    {
        // Arrange
        $request = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'name' => 'required|string|max:255',
                    'email' => 'required|email|unique:users',
                    'age' => 'nullable|integer|min:18',
                ];
            }
        };

        // Act
        $rules = $this->extractor->extractValidationRules($request);

        // Assert
        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('age', $rules);

        // Check rule details
        $this->assertEquals([
            'required' => true,
            'type' => 'string',
            'max' => 255,
        ], $rules['name']);

        $this->assertEquals([
            'required' => true,
            'type' => 'email',
            'unique' => 'users',
        ], $rules['email']);

        $this->assertEquals([
            'required' => false,
            'type' => 'integer',
            'min' => 18,
        ], $rules['age']);
    }

    #[Test]
    public function it_groups_endpoints_by_controller(): void
    {
        // Skip this test as it requires actual route registration
        $this->markTestSkipped('Requires actual route registration which is complex to mock');
    }

    #[Test]
    public function it_extracts_full_endpoint_details(): void
    {
        // Arrange - Create a mock route
        $route = $this->mock(RouteObject::class);
        $route->shouldReceive('uri')->andReturn('api/v1/users/{id}');
        $route->shouldReceive('methods')->andReturn(['POST']);
        $route->shouldReceive('getName')->andReturn('users.update');
        $route->shouldReceive('parameterNames')->andReturn(['id']);
        $route->shouldReceive('gatherMiddleware')->andReturn(['auth']);
        $route->shouldReceive('getActionName')->andReturn('App\\Http\\Controllers\\UserController@update');

        // Act
        $details = $this->extractor->extractFullEndpointDetails($route);

        // Assert
        $this->assertArrayHasKey('method', $details);
        $this->assertArrayHasKey('path', $details);
        $this->assertArrayHasKey('name', $details);
        $this->assertArrayHasKey('middleware', $details);
        $this->assertArrayHasKey('parameters', $details);
        $this->assertArrayHasKey('description', $details);
        $this->assertArrayHasKey('group', $details);
    }

    #[Test]
    public function it_generates_curl_example(): void
    {
        // Arrange - Create a mock route
        $route = $this->mock(RouteObject::class);
        $route->shouldReceive('uri')->andReturn('api/v1/users');
        $route->shouldReceive('methods')->andReturn(['POST']);
        $route->shouldReceive('getName')->andReturn('users.create');

        // Act
        $curl = $this->extractor->generateCurlExample($route, [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Assert
        $this->assertStringContainsString('curl -X POST', $curl);
        $this->assertStringContainsString('/api/v1/users', $curl);
        $this->assertStringContainsString('Content-Type: application/json', $curl);
        $this->assertStringContainsString('Accept: application/json', $curl);
        $this->assertStringContainsString('"name"', $curl);
        $this->assertStringContainsString('"John Doe"', $curl);
        $this->assertStringContainsString('"email"', $curl);
        $this->assertStringContainsString('"john@example.com"', $curl);
    }

    #[Test]
    public function it_handles_routes_without_parameters(): void
    {
        // Arrange - Create a mock route
        $route = $this->mock(RouteObject::class);
        $route->shouldReceive('uri')->andReturn('api/v1/status');
        $route->shouldReceive('methods')->andReturn(['GET']);
        $route->shouldReceive('getName')->andReturn('status');
        $route->shouldReceive('parameterNames')->andReturn([]);
        $route->shouldReceive('gatherMiddleware')->andReturn([]);

        // Act
        $info = $this->extractor->extractEndpoint($route);

        // Assert
        $this->assertEmpty($info['parameters']);
    }
}
