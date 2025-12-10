<?php

namespace Tests\Feature\Http\Controllers\AdminPanel;

use App\Actions\AdminPanel\VerifyMfaAction;
use App\Enums\IDP\RoleDefaults;
use App\Enums\IDP\TeamTypes;
use App\Models\Role;
use App\Models\Team;
use App\Services\CognitoAuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('admin-panel')]
#[Group('auth')]
class MfaAuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    private MockInterface $cognitoService;

    private VerifyMfaAction $verifyMfaAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cognitoService = $this->mock(CognitoAuthService::class);
        $this->verifyMfaAction = new VerifyMfaAction($this->cognitoService);
    }

    #[Test]
    public function it_returns_mfa_challenge_when_required(): void
    {

        ModelFactory::createUser([
            'email' => 'admin@test.com',
            'cognito_id' => 'test-cognito-id',
        ]);

        $this->cognitoService
            ->shouldReceive('authenticate')
            ->once()
            ->with('admin@test.com', 'password123')
            ->andReturn([
                'success' => true,
                'requires_mfa' => true,
                'challenge_name' => 'SMS_MFA',
                'session' => 'mock-session-token',
                'destination' => '+33*****1234',
            ]);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJson([
                'requires_mfa' => true,
                'challenge_name' => 'SMS_MFA',
                'session' => 'mock-session-token',
                'destination' => '+33*****1234',
                'username' => 'admin@test.com',
            ]);
    }

    #[Test]
    public function it_verifies_mfa_code_successfully(): void
    {

        $user = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'cognito_id' => 'test-cognito-id',
        ]);

        // Ensure GOD role exists
        setPermissionsTeamId($user->team_id);
        Role::firstOrCreate(
            [
                'name' => RoleDefaults::GOD,
                'guard_name' => 'api',
                'team_id' => $user->team_id,
            ]);
        $user->assignRole(RoleDefaults::GOD);

        $this->cognitoService
            ->shouldReceive('verifyMfaCode')
            ->once()
            ->with('admin@test.com', '123456', 'mock-session-token')
            ->andReturn([
                'success' => true,
                'access_token' => 'mock-access-token',
                'id_token' => 'mock-id-token',
                'refresh_token' => 'mock-refresh-token',
                'expires_in' => 3600,
            ]);

        $this->cognitoService
            ->shouldReceive('getUserFromToken')
            ->once()
            ->with('mock-access-token')
            ->andReturn($user);

        $response = $this->postJson('/api/v1/admin/auth/verify-mfa', [
            'username' => 'admin@test.com',
            'mfa_code' => '123456',
            'session' => 'mock-session-token',
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
    public function it_rejects_invalid_mfa_code(): void
    {

        Team::firstOrCreate(
            ['type' => TeamTypes::GLOBAL],
            ['name' => 'Global Team', 'slug' => 'global-team', 'type' => TeamTypes::GLOBAL]
        );

        $this->cognitoService
            ->shouldReceive('verifyMfaCode')
            ->once()
            ->with('admin@test.com', '000000', 'mock-session-token')
            ->andReturn([
                'success' => false,
                'error' => 'Invalid verification code',
            ]);

        $response = $this->postJson('/api/v1/admin/auth/verify-mfa', [
            'username' => 'admin@test.com',
            'mfa_code' => '000000',
            'session' => 'mock-session-token',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'error' => 'Invalid verification code',
            ]);
    }

    #[Test]
    public function it_validates_mfa_request_data(): void
    {
        $response = $this->postJson('/api/v1/admin/auth/verify-mfa', [
            'username' => 'invalid-email',
            'mfa_code' => '12345', // Too short
            'session' => '',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'mfa_code', 'session']);
    }

    #[Test]
    public function it_rejects_user_without_god_role(): void
    {

        $user = ModelFactory::createUser([
            'email' => 'user@test.com',
            'cognito_id' => 'test-cognito-id',
        ]);

        // Don't assign GOD role

        $this->cognitoService
            ->shouldReceive('verifyMfaCode')
            ->once()
            ->andReturn([
                'success' => true,
                'access_token' => 'mock-access-token',
                'id_token' => 'mock-id-token',
                'refresh_token' => 'mock-refresh-token',
                'expires_in' => 3600,
            ]);

        $this->cognitoService
            ->shouldReceive('getUserFromToken')
            ->once()
            ->andReturn($user);

        $response = $this->postJson('/api/v1/admin/auth/verify-mfa', [
            'username' => 'user@test.com',
            'mfa_code' => '123456',
            'session' => 'mock-session-token',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'error' => 'Access denied. Admin panel is restricted to GOD users only.',
            ]);
    }

    #[Test]
    public function it_shows_mfa_view(): void
    {
        $response = $this->get('/admin-panel/auth/mfa');

        $response->assertOk()
            ->assertViewIs('admin-panel.auth.mfa');
    }
}
