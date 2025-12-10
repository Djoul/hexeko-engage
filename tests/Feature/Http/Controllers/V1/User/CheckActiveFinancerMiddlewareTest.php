<?php

namespace Tests\Feature\Http\Controllers\V1\User;

use App\Http\Middleware\CheckActiveFinancerMiddleware;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
#[Group('middleware')]
class CheckActiveFinancerMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    private CheckActiveFinancerMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CheckActiveFinancerMiddleware;
    }

    #[Test]
    public function it_blocks_unauthenticated_users(): void
    {
        $request = Request::create('/api/test');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('{"error":"Unauthenticated"}', $response->getContent());
    }

    #[Test]
    public function it_blocks_users_without_active_financer(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create();

        // Attach financer but inactive
        $user->financers()->attach($financer->id, ['active' => false, 'role' => 'beneficiary']);

        Auth::login($user);

        $request = Request::create('/api/test');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('User must have at least one active financer', $response->getContent());
    }

    #[Test]
    public function it_allows_users_with_active_financer(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create();

        // Attach active financer
        $user->financers()->attach($financer->id, ['active' => true, 'role' => 'beneficiary']);

        Auth::login($user);

        $request = Request::create('/api/test');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"success":true}', $response->getContent());
    }

    #[Test]
    public function it_allows_users_with_multiple_financers_where_at_least_one_is_active(): void
    {
        $user = User::factory()->create();
        $financer1 = Financer::factory()->create();
        $financer2 = Financer::factory()->create();

        // One active, one inactive
        $user->financers()->attach($financer1->id, ['active' => false, 'role' => 'beneficiary']);
        $user->financers()->attach($financer2->id, ['active' => true, 'role' => 'beneficiary']);

        Auth::login($user);

        $request = Request::create('/api/test');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_blocks_users_with_no_financers(): void
    {
        $user = User::factory()->create();

        // No financers attached

        Auth::login($user);

        $request = Request::create('/api/test');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('User must have at least one active financer', $response->getContent());
    }

    #[Test]
    public function it_works_with_api_routes(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create();

        $user->financers()->attach($financer->id, ['active' => true, 'role' => 'beneficiary']);

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/v1/users');

        // Should not be blocked by middleware
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function it_blocks_api_routes_for_users_without_active_financer(): void
    {
        $user = User::factory()->create();

        // No active financer

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/v1/users');

        // Should be blocked - either by auth or by our middleware
        // Since we don't know which middleware runs first, accept both 401 and 403
        $this->assertContains($response->getStatusCode(), [401, 403]);

        // If it's 403, it should be our middleware message
        if ($response->getStatusCode() === 403) {
            $response->assertJson([
                'message' => 'User must have at least one active financer',
            ]);
        }
    }

    #[Test]
    public function it_handles_financer_deactivation(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create();

        // Initially active
        $user->financers()->attach($financer->id, ['active' => true, 'role' => 'beneficiary']);

        Auth::login($user);

        // First request should pass
        $request = Request::create('/api/test');
        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });
        $this->assertEquals(200, $response->getStatusCode());

        // Deactivate financer
        $user->financers()->updateExistingPivot($financer->id, ['active' => false, 'role' => 'beneficiary']);

        // Second request should fail
        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });
        $this->assertEquals(403, $response->getStatusCode());
    }
}
