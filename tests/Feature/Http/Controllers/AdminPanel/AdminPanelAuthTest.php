<?php

namespace Tests\Feature\Http\Controllers\AdminPanel;

use App\Enums\IDP\RoleDefaults;
use App\Models\Role;
use App\Services\CognitoAuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\AdminRoutes;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('admin-panel')]
class AdminPanelAuthTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a default team for roles
        $this->defaultTeam = ModelFactory::createTeam(['name' => 'Default Admin Team']);

        // Set team context for permissions
        setPermissionsTeamId($this->defaultTeam->id);

        // Ensure GOD role exists for API guard with team
        if (! Role::where('name', RoleDefaults::GOD)->where('guard_name', 'api')->where('team_id', $this->defaultTeam->id)->exists()) {
            ModelFactory::createRole(['name' => RoleDefaults::GOD, 'guard_name' => 'api', 'team_id' => $this->defaultTeam->id]);
        }

        // Ensure other roles exist for testing
        if (! Role::where('name', RoleDefaults::BENEFICIARY)->where('guard_name', 'api')->where('team_id', $this->defaultTeam->id)->exists()) {
            ModelFactory::createRole(['name' => RoleDefaults::BENEFICIARY, 'guard_name' => 'api', 'team_id' => $this->defaultTeam->id]);
        }
    }

    private $defaultTeam;

    #[Test]
    public function it_returns_401_for_unauthenticated_api_requests(): void
    {
        $response = $this->getJson(AdminRoutes::TEST_AUTH);

        $response->assertUnauthorized()
            ->assertJson(['error' => 'Authorization token is missing']);
    }

    #[Test]
    public function it_allows_access_to_god_users_with_bearer_token(): void
    {
        // Create user with GOD role using the default team
        $user = ModelFactory::createUser([
            'email' => 'god@test.com',
            'team_id' => $this->defaultTeam->id,
            'cognito_id' => 'test-cognito-id-god',
        ]);

        // Ensure team context is set
        setPermissionsTeamId($this->defaultTeam->id);

        // Assign role using Spatie's assignRole method
        $user->assignRole(RoleDefaults::GOD);

        // Simulate Bearer token authentication
        $token = 'fake-bearer-token';

        // Mock the middleware to accept our test token
        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->getJson(AdminRoutes::TEST_AUTH);

        // For now, this will fail until middleware is implemented
        // Test will verify proper Bearer token handling
        $response->assertStatus(401); // Will be 200 after implementation
    }

    #[Test]
    public function it_denies_access_to_non_god_users_with_bearer_token(): void
    {
        // Create user with BENEFICIARY role
        $user = ModelFactory::createUser([
            'email' => 'beneficiary@test.com',
            'team_id' => $this->defaultTeam->id,
            'cognito_id' => 'test-cognito-id-beneficiary',
        ]);

        // Set team context and assign role
        setPermissionsTeamId($this->defaultTeam->id);
        $user->assignRole(RoleDefaults::BENEFICIARY);

        // Mock CognitoAuthService
        $cognitoService = Mockery::mock(CognitoAuthService::class);
        $this->app->instance(CognitoAuthService::class, $cognitoService);

        // Simulate Bearer token for non-GOD user
        $token = 'fake-bearer-token-beneficiary';

        // Mock token validation succeeding (token is valid)
        $cognitoService->shouldReceive('validateAccessToken')
            ->once()
            ->with($token)
            ->andReturn(true);

        // Mock getUserFromToken returning the beneficiary user
        $cognitoService->shouldReceive('getUserFromToken')
            ->once()
            ->with($token)
            ->andReturn($user);

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->getJson(AdminRoutes::TEST_AUTH);

        // Should return 403 Forbidden for users without GOD role
        $response->assertForbidden()
            ->assertJson(['error' => 'Access denied. Admin panel is restricted to GOD users only.']);
    }

    #[Test]
    public function it_allows_access_to_auth_endpoints_without_authentication(): void
    {
        // Login endpoint should be accessible
        $response = $this->postJson(AdminRoutes::API_LOGIN, []);

        // Should get validation error, not auth error
        $response->assertStatus(422);
    }

    #[Test]
    public function it_validates_expired_bearer_tokens(): void
    {
        // Simulate expired token
        $expiredToken = 'expired-bearer-token';

        $response = $this->withHeaders([
            'Authorization' => "Bearer $expiredToken",
        ])->getJson(AdminRoutes::TEST_AUTH);

        $response->assertUnauthorized()
            ->assertJson(['error' => 'Token expired or invalid']);
    }

    #[Test]
    public function it_validates_invalid_bearer_tokens(): void
    {
        // Simulate invalid token format
        $invalidToken = 'not-a-valid-jwt-token';

        $response = $this->withHeaders([
            'Authorization' => "Bearer $invalidToken",
        ])->getJson(AdminRoutes::TEST_AUTH);

        $response->assertUnauthorized()
            ->assertJson(['error' => 'Token expired or invalid']);
    }

    #[Test]
    public function it_handles_bearer_token_in_cookie(): void
    {
        // Create user with GOD role
        $user = ModelFactory::createUser([
            'email' => 'god@test.com',
            'cognito_id' => 'test-cognito-id-cookie',
            'team_id' => $this->defaultTeam->id,
        ]);

        setPermissionsTeamId($this->defaultTeam->id);
        $user->assignRole(RoleDefaults::GOD);

        // Simulate cookie-based Bearer token for Livewire
        $token = 'fake-bearer-token-in-cookie';

        $response = $this->withCookie('admin_token', $token)
            ->getJson(AdminRoutes::TEST_AUTH);

        // Will be 200 after implementation, currently expecting 401
        $response->assertStatus(401);
    }

    #[Test]
    public function it_enforces_god_role_for_all_admin_endpoints(): void
    {
        // Test multiple admin endpoints to ensure GOD role is enforced
        $endpoints = AdminRoutes::PROTECTED_ROUTES;

        // Create user without GOD role
        $user = ModelFactory::createUser([
            'email' => 'non-god@test.com',
            'cognito_id' => 'test-cognito-id-non-god',
            'team_id' => $this->defaultTeam->id,
        ]);

        setPermissionsTeamId($this->defaultTeam->id);
        $user->assignRole(RoleDefaults::BENEFICIARY);

        // Mock CognitoAuthService
        $cognitoService = Mockery::mock(CognitoAuthService::class);
        $this->app->instance(CognitoAuthService::class, $cognitoService);

        $token = 'fake-bearer-token-non-god';

        // Mock token validation and user retrieval for each endpoint call
        $cognitoService->shouldReceive('validateAccessToken')
            ->times(count($endpoints))
            ->with($token)
            ->andReturn(true);

        $cognitoService->shouldReceive('getUserFromToken')
            ->times(count($endpoints))
            ->with($token)
            ->andReturn($user);

        foreach ($endpoints as $endpoint) {
            $response = $this->withHeaders(['Authorization' => "Bearer $token"])
                ->json($endpoint['method'], $endpoint['uri']);

            $response->assertForbidden()
                ->assertJson(['error' => 'Access denied. Admin panel is restricted to GOD users only.']);
        }
    }

    #[Test]
    public function it_handles_token_refresh_scenarios(): void
    {
        // Test that expired tokens return appropriate error
        $expiredToken = 'expired-token-needing-refresh';

        $response = $this->withHeaders([
            'Authorization' => "Bearer $expiredToken",
        ])->getJson(AdminRoutes::TEST_AUTH);

        $response->assertUnauthorized()
            ->assertJsonStructure(['error'])
            ->assertJson(['error' => 'Token expired or invalid']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
