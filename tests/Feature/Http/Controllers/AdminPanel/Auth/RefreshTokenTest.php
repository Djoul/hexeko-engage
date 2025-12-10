<?php

namespace Tests\Feature\Http\Controllers\AdminPanel\Auth;

use App\Actions\AdminPanel\RefreshTokenAction;
use App\DTOs\Auth\TokenResponseDTO;
use App\Enums\IDP\RoleDefaults;
use App\Exceptions\AuthenticationException;
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
class RefreshTokenTest extends TestCase
{
    use DatabaseTransactions;

    const REFRESH_URI = '/api/v1/admin/auth/refresh';

    private MockInterface $refreshTokenAction;

    private $defaultTeam;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a default team for roles
        $this->defaultTeam = ModelFactory::createTeam(['name' => 'Admin Refresh Team']);
        setPermissionsTeamId($this->defaultTeam->id);

        // Ensure GOD role exists
        if (! Role::where('name', RoleDefaults::GOD)->where('guard_name', 'api')->where('team_id', $this->defaultTeam->id)->exists()) {
            ModelFactory::createRole(['name' => RoleDefaults::GOD, 'guard_name' => 'api', 'team_id' => $this->defaultTeam->id]);
        }

        // Mock action
        $this->refreshTokenAction = Mockery::mock(RefreshTokenAction::class);
        $this->app->instance(RefreshTokenAction::class, $this->refreshTokenAction);
    }

    #[Test]
    public function it_refreshes_access_token_successfully(): void
    {
        $refreshToken = 'valid-refresh-token';

        $mockResponse = new TokenResponseDTO(
            accessToken: 'new-access-token',
            idToken: 'new-id-token',
            refreshToken: $refreshToken,
            expiresIn: 3600,
            tokenType: 'Bearer',
            user: [
                'id' => 'test-user-id',
                'email' => 'admin@test.com',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'roles' => ['god'],
                'permissions' => [],
            ]
        );

        $this->refreshTokenAction
            ->shouldReceive('execute')
            ->once()
            ->with($refreshToken)
            ->andReturn($mockResponse);

        $response = $this->postJson(self::REFRESH_URI, [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'access_token',
                'id_token',
                'refresh_token',
                'expires_in',
                'token_type',
                'user',
            ]);
    }

    #[Test]
    public function it_handles_invalid_refresh_token(): void
    {
        $invalidToken = 'invalid-refresh-token';

        $this->refreshTokenAction
            ->shouldReceive('execute')
            ->once()
            ->with($invalidToken)
            ->andThrow(new AuthenticationException('Invalid refresh token'));

        $response = $this->postJson(self::REFRESH_URI, [
            'refresh_token' => $invalidToken,
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'error' => 'Invalid refresh token',
            ]);
    }

    #[Test]
    public function it_handles_expired_refresh_token(): void
    {
        $expiredToken = 'expired-refresh-token';

        $this->refreshTokenAction
            ->shouldReceive('execute')
            ->once()
            ->with($expiredToken)
            ->andThrow(new AuthenticationException('Refresh token expired'));

        $response = $this->postJson(self::REFRESH_URI, [
            'refresh_token' => $expiredToken,
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'error' => 'Refresh token expired',
            ]);
    }

    #[Test]
    public function it_validates_required_refresh_token(): void
    {
        $response = $this->postJson(self::REFRESH_URI, []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['refresh_token']);
    }

    #[Test]
    public function it_handles_cognito_service_errors(): void
    {
        $refreshToken = 'valid-refresh-token';

        $this->refreshTokenAction
            ->shouldReceive('execute')
            ->once()
            ->with($refreshToken)
            ->andThrow(new Exception('Authentication service unavailable'));

        $response = $this->postJson(self::REFRESH_URI, [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Token refresh failed',
            ]);
    }

    #[Test]
    public function it_validates_user_still_has_god_role(): void
    {
        $financer = ModelFactory::createFinancer(['name' => 'Test Financer']);
        // Create admin user with GOD role
        $admin = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $admin->assignRole(RoleDefaults::GOD);

        $refreshToken = 'valid-refresh-token';

        // Mock successful token refresh for user WITH god role
        $successResponse = new TokenResponseDTO(
            accessToken: 'new-access-token',
            idToken: 'new-id-token',
            refreshToken: $refreshToken,
            expiresIn: 3600,
            tokenType: 'Bearer',
            user: [
                'id' => $admin->id,
                'email' => $admin->email,
                'first_name' => 'Admin',
                'last_name' => 'User',
                'roles' => ['god'],
                'permissions' => [],
            ]
        );

        $this->refreshTokenAction
            ->shouldReceive('execute')
            ->once()
            ->with($refreshToken)
            ->andReturn($successResponse);

        $response = $this->postJson(self::REFRESH_URI, [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertOk();

        // Now test what happens when user loses GOD role (separate mock)
        $this->refreshTokenAction
            ->shouldReceive('execute')
            ->once()
            ->with($refreshToken)
            ->andThrow(new AuthenticationException('Access denied. Admin panel is restricted to GOD users only.'));

        $response = $this->postJson(self::REFRESH_URI, [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'error' => 'Access denied. Admin panel is restricted to GOD users only.',
            ]);
    }

    #[Test]
    public function it_does_not_require_bearer_token_for_refresh(): void
    {
        // Refresh endpoint should not require Bearer token authentication
        // It uses the refresh token itself for authentication
        $refreshToken = 'valid-refresh-token';

        $mockResponse = new TokenResponseDTO(
            accessToken: 'new-access-token',
            idToken: 'new-id-token',
            refreshToken: $refreshToken,
            expiresIn: 3600,
            tokenType: 'Bearer',
            user: [
                'id' => 'test-user-id',
                'email' => 'admin@test.com',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'roles' => ['god'],
                'permissions' => [],
            ]
        );

        $this->refreshTokenAction
            ->shouldReceive('execute')
            ->once()
            ->with($refreshToken)
            ->andReturn($mockResponse);

        // Call without Authorization header
        $response = $this->postJson(self::REFRESH_URI, [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertOk();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
