<?php

namespace Tests\Feature\Http\Controllers\AdminPanel\Auth;

use App\Actions\AdminPanel\AuthenticateAdminAction;
use App\Actions\AdminPanel\VerifyMfaAction;
use App\DTOs\Auth\TokenResponseDTO as AuthTokenResponseDTO;
use App\Enums\IDP\RoleDefaults;
use App\Exceptions\AuthenticationException;
use App\Models\Role;
use App\Services\CognitoAuthService;
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
class CognitoLoginTest extends TestCase
{
    use DatabaseTransactions;

    const LOGIN_URI = '/api/v1/admin/auth/login';

    const VERIFY_MFA_URI = '/api/v1/admin/auth/verify-mfa';

    private MockInterface $cognitoService;

    private MockInterface $authenticateAction;

    private $defaultTeam;

    private $financer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a default team for roles
        $this->defaultTeam = ModelFactory::createTeam(['name' => 'Admin Auth Team']);
        setPermissionsTeamId($this->defaultTeam->id);

        // Create a financer for users
        $this->financer = ModelFactory::createFinancer(['name' => 'Test Financer']);

        // Ensure GOD role exists
        if (! Role::where('name', RoleDefaults::GOD)->where('guard_name', 'api')->where('team_id', $this->defaultTeam->id)->exists()) {
            ModelFactory::createRole(['name' => RoleDefaults::GOD, 'guard_name' => 'api', 'team_id' => $this->defaultTeam->id]);
        }

        // Mock services
        $this->cognitoService = Mockery::mock(CognitoAuthService::class);
        $this->authenticateAction = Mockery::mock(AuthenticateAdminAction::class);

        $this->app->instance(CognitoAuthService::class, $this->cognitoService);
        $this->app->instance(AuthenticateAdminAction::class, $this->authenticateAction);
    }

    #[Test]
    public function it_authenticates_admin_user_with_god_role(): void
    {
        // Create admin user with GOD role - properly linked to financer
        $admin = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'cognito_id' => 'test-cognito-id',
            'team_id' => $this->defaultTeam->id,
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);

        $admin->assignRole(RoleDefaults::GOD);

        // Mock Cognito service for direct authentication check
        $this->cognitoService
            ->shouldReceive('authenticate')
            ->once()
            ->with('admin@test.com', 'TestPassword123!')
            ->andReturn([
                'success' => true,
                'requires_mfa' => false,
            ]);

        // Mock AuthenticateAdminAction with proper DTO structure
        $tokenResponse = new AuthTokenResponseDTO(
            accessToken: 'fake-access-token',
            idToken: 'fake-id-token',
            refreshToken: 'fake-refresh-token',
            expiresIn: 3600,
            tokenType: 'Bearer',
            user: [
                'id' => $admin->id,
                'email' => 'admin@test.com',
                'first_name' => $admin->first_name,
                'last_name' => $admin->last_name,
                'roles' => $admin->getRoleNames()->toArray(),
            ]
        );

        $this->authenticateAction
            ->shouldReceive('execute')
            ->once()
            ->andReturn($tokenResponse);

        $response = $this->postJson(self::LOGIN_URI, [
            'email' => 'admin@test.com',
            'password' => 'TestPassword123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'access_token',
                'id_token',
                'refresh_token',
                'expires_in',
                'token_type',
                'user' => ['id', 'email', 'first_name', 'last_name', 'roles'],
            ])
            ->assertJson([
                'access_token' => 'fake-access-token',
                'token_type' => 'Bearer',
                'user' => [
                    'email' => 'admin@test.com',
                ],
            ]);

        // Verify cookie is set
        $response->assertCookie('admin_token', 'fake-access-token');
    }

    #[Test]
    public function it_handles_mfa_challenge_during_login(): void
    {
        // Create admin user with GOD role
        $admin = ModelFactory::createUser([
            'email' => 'admin-mfa@test.com',
            'cognito_id' => 'test-cognito-id-mfa',
            'team_id' => $this->defaultTeam->id,
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);

        $admin->assignRole(RoleDefaults::GOD);

        // Mock Cognito service to return MFA challenge
        $this->cognitoService
            ->shouldReceive('authenticate')
            ->once()
            ->with('admin-mfa@test.com', 'TestPassword123!')
            ->andReturn([
                'success' => true,
                'requires_mfa' => true,
                'challenge_name' => 'SMS_MFA',
                'session' => 'cognito-session-token',
                'destination' => '+33***123',
            ]);

        $response = $this->postJson(self::LOGIN_URI, [
            'email' => 'admin-mfa@test.com',
            'password' => 'TestPassword123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'requires_mfa',
                'challenge_name',
                'session',
                'destination',
                'username',
            ])
            ->assertJson([
                'requires_mfa' => true,
                'challenge_name' => 'SMS_MFA',
                'session' => 'cognito-session-token',
                'destination' => '+33***123',
                'username' => 'admin-mfa@test.com',
            ]);
    }

    #[Test]
    public function it_verifies_mfa_and_completes_authentication(): void
    {
        // Create admin user with GOD role
        $admin = ModelFactory::createUser([
            'email' => 'admin-mfa@test.com',
            'cognito_id' => 'test-cognito-id-mfa',
            'team_id' => $this->defaultTeam->id,
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);

        $admin->assignRole(RoleDefaults::GOD);

        // Mock CognitoAuthService for MFA verification
        $this->cognitoService
            ->shouldReceive('verifyMfaCode')
            ->once()
            ->with('admin-mfa@test.com', '123456', 'cognito-session-token')
            ->andReturn([
                'success' => true,
                'access_token' => 'fake-access-token-mfa',
                'id_token' => 'fake-id-token-mfa',
                'refresh_token' => 'fake-refresh-token-mfa',
                'expires_in' => 3600,
            ]);

        $this->cognitoService
            ->shouldReceive('getUserFromToken')
            ->once()
            ->with('fake-access-token-mfa')
            ->andReturn($admin);

        // Don't mock VerifyMfaAction - let it use the mocked CognitoService
        // This avoids the DTO type mismatch issue
        $this->app->instance(CognitoAuthService::class, $this->cognitoService);

        // Create real VerifyMfaAction with mocked dependencies
        $this->app->bind(VerifyMfaAction::class, function (): VerifyMfaAction {
            return new VerifyMfaAction($this->cognitoService);
        });

        $response = $this->postJson(self::VERIFY_MFA_URI, [
            'username' => 'admin-mfa@test.com',
            'mfa_code' => '123456',
            'session' => 'cognito-session-token',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'access_token',
                'id_token',
                'refresh_token',
                'expires_in',
                'token_type',
                'user' => ['id', 'email', 'first_name', 'last_name', 'roles'],
            ])
            ->assertJson([
                'access_token' => 'fake-access-token-mfa',
                'token_type' => 'Bearer',
                'user' => [
                    'email' => 'admin-mfa@test.com',
                ],
            ]);

        // Verify cookie is set
        $response->assertCookie('admin_token', 'fake-access-token-mfa');
    }

    #[Test]
    public function it_handles_invalid_mfa_code(): void
    {
        // Mock CognitoAuthService to fail MFA verification
        $this->cognitoService
            ->shouldReceive('verifyMfaCode')
            ->once()
            ->with('admin-mfa@test.com', '000000', 'cognito-session-token')
            ->andReturn([
                'success' => false,
                'error' => 'Invalid MFA code',
            ]);

        // Don't mock VerifyMfaAction - let it use the mocked CognitoService
        $this->app->instance(CognitoAuthService::class, $this->cognitoService);

        // Create real VerifyMfaAction with mocked dependencies
        $this->app->bind(VerifyMfaAction::class, function (): VerifyMfaAction {
            return new VerifyMfaAction($this->cognitoService);
        });

        $response = $this->postJson(self::VERIFY_MFA_URI, [
            'username' => 'admin-mfa@test.com',
            'mfa_code' => '000000',
            'session' => 'cognito-session-token',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'error' => 'Invalid MFA code',
            ]);
    }

    #[Test]
    public function it_denies_login_for_non_god_users(): void
    {
        // Create user without GOD role
        ModelFactory::createUser([
            'email' => 'regular@test.com',
            'cognito_id' => 'test-cognito-id-regular',
            'team_id' => $this->defaultTeam->id,
        ]);

        // Mock successful Cognito authentication first
        $this->cognitoService
            ->shouldReceive('authenticate')
            ->once()
            ->with('regular@test.com', 'TestPassword123!')
            ->andReturn([
                'success' => true,
                'requires_mfa' => false,
            ]);

        // Mock AuthenticateAdminAction throwing exception for non-GOD user
        $this->authenticateAction
            ->shouldReceive('execute')
            ->once()
            ->andThrow(new AuthenticationException('Access denied. Admin panel is restricted to GOD users only.'));

        $response = $this->postJson(self::LOGIN_URI, [
            'email' => 'regular@test.com',
            'password' => 'TestPassword123!',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'error' => 'Access denied. Admin panel is restricted to GOD users only.',
            ]);
    }

    #[Test]
    public function it_handles_invalid_credentials(): void
    {
        // Mock Cognito service to fail authentication
        $this->cognitoService
            ->shouldReceive('authenticate')
            ->once()
            ->with('admin@test.com', 'WrongPassword')
            ->andReturn([
                'success' => false,
                'error' => 'Invalid credentials',
            ]);

        $response = $this->postJson(self::LOGIN_URI, [
            'email' => 'admin@test.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'error' => 'Invalid credentials',
            ]);
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        $response = $this->postJson(self::LOGIN_URI, []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    #[Test]
    public function it_validates_email_format(): void
    {
        $response = $this->postJson(self::LOGIN_URI, [
            'email' => 'not-an-email',
            'password' => 'TestPassword123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function it_handles_user_not_found_in_database(): void
    {
        // Mock Cognito service to succeed but user not in database
        $this->cognitoService
            ->shouldReceive('authenticate')
            ->once()
            ->with('unknown@test.com', 'TestPassword123!')
            ->andReturn([
                'success' => true,
                'requires_mfa' => false,
            ]);

        // Mock AuthenticateAdminAction throwing exception for missing user
        $this->authenticateAction
            ->shouldReceive('execute')
            ->once()
            ->andThrow(new AuthenticationException('User not found in application database'));

        $response = $this->postJson(self::LOGIN_URI, [
            'email' => 'unknown@test.com',
            'password' => 'TestPassword123!',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'error' => 'User not found in application database',
            ]);
    }

    #[Test]
    public function it_handles_cognito_service_errors(): void
    {
        // Mock Cognito service to throw an exception
        $this->cognitoService
            ->shouldReceive('authenticate')
            ->once()
            ->with('admin@test.com', 'TestPassword123!')
            ->andThrow(new Exception('Authentication service configuration error'));

        $response = $this->postJson(self::LOGIN_URI, [
            'email' => 'admin@test.com',
            'password' => 'TestPassword123!',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Authentication failed',
            ]);
    }

    #[Test]
    public function it_validates_mfa_required_fields(): void
    {
        $response = $this->postJson(self::VERIFY_MFA_URI, []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'mfa_code', 'session']);
    }

    #[Test]
    public function it_validates_mfa_code_format(): void
    {
        $response = $this->postJson(self::VERIFY_MFA_URI, [
            'username' => 'admin@test.com',
            'mfa_code' => '12345', // Too short
            'session' => 'cognito-session-token',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['mfa_code']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
