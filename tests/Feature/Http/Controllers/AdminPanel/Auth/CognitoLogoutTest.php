<?php

namespace Tests\Feature\Http\Controllers\AdminPanel\Auth;

use App\Actions\AdminPanel\LogoutAction;
use App\Enums\IDP\RoleDefaults;
use App\Models\Role;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('admin-panel')]
#[Group('auth')]
class CognitoLogoutTest extends TestCase
{
    use DatabaseTransactions;

    const LOGOUT_URI = '/api/v1/admin/auth/logout';

    private MockInterface $logoutAction;

    private $defaultTeam;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a default team for roles
        $this->defaultTeam = ModelFactory::createTeam(['name' => 'Admin Logout Team']);
        setPermissionsTeamId($this->defaultTeam->id);

        // Ensure GOD role exists
        if (! Role::where('name', RoleDefaults::GOD)->where('guard_name', 'api')->where('team_id', $this->defaultTeam->id)->exists()) {
            ModelFactory::createRole(['name' => RoleDefaults::GOD, 'guard_name' => 'api', 'team_id' => $this->defaultTeam->id]);
        }

        // Mock action
        $this->logoutAction = Mockery::mock(LogoutAction::class);
        $this->app->instance(LogoutAction::class, $this->logoutAction);
    }

    #[Test]
    public function it_logs_out_successfully_with_valid_token(): void
    {
        $bearerToken = 'valid-bearer-token';

        // Create admin user with GOD role
        $admin = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'cognito_id' => 'test-cognito-id',
            'team_id' => $this->defaultTeam->id,
        ]);

        $admin->assignRole(RoleDefaults::GOD);

        $this->logoutAction
            ->shouldReceive('execute')
            ->once()
            ->with($bearerToken)
            ->andReturn([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer $bearerToken",
        ])->postJson(self::LOGOUT_URI);

        $response->assertOk()
            ->assertJson([
                'message' => 'Logged out successfully',
            ]);
    }

    #[Test]
    public function it_logs_out_successfully_without_token(): void
    {
        // No Bearer token provided - logout still succeeds
        $response = $this->postJson(self::LOGOUT_URI);

        $response->assertOk()
            ->assertJson([
                'message' => 'Logged out successfully',
            ]);
    }

    #[Test]
    public function it_handles_invalid_bearer_token_on_logout(): void
    {
        $invalidToken = 'invalid-bearer-token';

        $this->logoutAction
            ->shouldReceive('execute')
            ->once()
            ->with($invalidToken)
            ->andThrow(new Exception('Token expired or invalid'));

        $response = $this->withHeaders([
            'Authorization' => "Bearer $invalidToken",
        ])->postJson(self::LOGOUT_URI);

        // Even with invalid token, logout returns success (token will expire naturally)
        $response->assertOk()
            ->assertJson([
                'message' => 'Logged out successfully',
            ]);
    }

    #[Test]
    public function it_clears_cookie_on_logout_for_web_requests(): void
    {
        $bearerToken = 'valid-bearer-token';

        $this->logoutAction
            ->shouldReceive('execute')
            ->once()
            ->with($bearerToken)
            ->andReturn([
                'message' => 'Logged out successfully',
            ]);

        // Simulate web request with cookie
        $response = $this->withCookie('admin_token', $bearerToken)
            ->withHeaders([
                'Authorization' => "Bearer $bearerToken",
            ])
            ->postJson(self::LOGOUT_URI);

        $response->assertOk()
            ->assertJson([
                'message' => 'Logged out successfully',
            ])
            ->assertCookie('admin_token', ''); // Cookie should be cleared
    }

    #[Test]
    public function it_handles_cognito_service_errors_gracefully(): void
    {
        $bearerToken = 'valid-bearer-token';

        $this->logoutAction
            ->shouldReceive('execute')
            ->once()
            ->with($bearerToken)
            ->andThrow(new Exception('Authentication service error'));

        $response = $this->withHeaders([
            'Authorization' => "Bearer $bearerToken",
        ])->postJson(self::LOGOUT_URI);

        // Even with Cognito errors, logout returns success (fail-safe design)
        $response->assertOk()
            ->assertJson([
                'message' => 'Logged out successfully',
            ]);
    }

    #[Test]
    public function it_allows_logout_without_god_role(): void
    {
        $bearerToken = 'valid-bearer-token-non-god';

        // Create user without GOD role
        ModelFactory::createUser([
            'email' => 'regular@test.com',
            'cognito_id' => 'test-cognito-id-regular',
            'team_id' => $this->defaultTeam->id,
        ]);

        // Logout action should be called - no role check on logout
        $this->logoutAction
            ->shouldReceive('execute')
            ->once()
            ->with($bearerToken)
            ->andReturn([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer $bearerToken",
        ])->postJson(self::LOGOUT_URI);

        // Logout succeeds regardless of role (no auth middleware on logout)
        $response->assertOk()
            ->assertJson([
                'message' => 'Logged out successfully',
            ]);
    }

    #[Test]
    public function it_returns_simple_success_message_on_logout(): void
    {
        $bearerToken = 'valid-bearer-token';

        $this->logoutAction
            ->shouldReceive('execute')
            ->once()
            ->with($bearerToken)
            ->andReturn([
                'success' => true,
                'message' => 'Logged out successfully',
                'tokens_revoked' => true,
            ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer $bearerToken",
        ])->postJson(self::LOGOUT_URI);

        // Controller only returns the message field, not the full response from action
        $response->assertOk()
            ->assertJson([
                'message' => 'Logged out successfully',
            ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
