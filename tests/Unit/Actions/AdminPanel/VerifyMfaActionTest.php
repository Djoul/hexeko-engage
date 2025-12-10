<?php

namespace Tests\Unit\Actions\AdminPanel;

use App\Actions\AdminPanel\VerifyMfaAction;
use App\DTOs\AdminPanel\TokenResponseDTO;
use App\Enums\IDP\RoleDefaults;
use App\Exceptions\AuthenticationException;
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
#[Group('mfa')]
#[Group('auth')]
class VerifyMfaActionTest extends TestCase
{
    use DatabaseTransactions;

    private MockInterface $cognitoService;

    private VerifyMfaAction $action;

    #[Test]
    public function it_verifies_mfa_and_returns_token_response(): void
    {
        //        $this->markTestSkipped('Test causes timeout - needs investigation');

        $team = ModelFactory::createTeam(['name' => 'Test Team']);
        $user = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'team_id' => $team->id,
        ]);

        // Ensure GOD role exists
        setPermissionsTeamId($team->id);
        Role::firstOrCreate(
            [
                'name' => RoleDefaults::GOD,
                'guard_name' => 'api',
                'team_id' => $team->id,
            ]);
        $user->assignRole(RoleDefaults::GOD);

        $cognitoResult = [
            'success' => true,
            'access_token' => 'mock-access-token',
            'id_token' => 'mock-id-token',
            'refresh_token' => 'mock-refresh-token',
            'expires_in' => 3600,
        ];

        $this->cognitoService
            ->shouldReceive('verifyMfaCode')
            ->once()
            ->with('admin@test.com', '123456', 'mock-session')
            ->andReturn($cognitoResult);

        $this->cognitoService
            ->shouldReceive('getUserFromToken')
            ->once()
            ->with('mock-access-token')
            ->andReturn($user);

        $result = $this->action->execute('admin@test.com', '123456', 'mock-session');

        $this->assertInstanceOf(TokenResponseDTO::class, $result);
        $this->assertEquals('mock-access-token', $result->accessToken);
        $this->assertEquals('mock-id-token', $result->idToken);
        $this->assertEquals('mock-refresh-token', $result->refreshToken);
        $this->assertEquals(3600, $result->expiresIn);
        $this->assertEquals('Bearer', $result->tokenType);
        $this->assertIsArray($result->user);
    }

    #[Test]
    public function it_throws_exception_when_mfa_verification_fails(): void
    {
        $this->cognitoService
            ->shouldReceive('verifyMfaCode')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Invalid verification code',
            ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid verification code');

        $this->action->execute('admin@test.com', '000000', 'mock-session');
    }

    #[Test]
    public function it_throws_exception_when_user_not_found(): void
    {
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
            ->andReturn(null);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('User not found in database');

        $this->action->execute('nonexistent@test.com', '123456', 'mock-session');
    }

    #[Test]
    public function it_throws_exception_when_user_lacks_god_role(): void
    {
        $user = ModelFactory::createUser([
            'email' => 'user@test.com',
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

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Access denied. Admin panel is restricted to GOD users only.');

        $this->action->execute('user@test.com', '123456', 'mock-session');
    }

    #[Test]
    public function it_configures_permission_context_with_user_team(): void
    {
        $team = ModelFactory::createTeam(['name' => 'User Team']);
        $user = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'team_id' => $team->id,
        ]);

        // Ensure GOD role exists
        setPermissionsTeamId($team->id);
        Role::firstOrCreate(
            [
                'name' => RoleDefaults::GOD,
                'guard_name' => 'api',
                'team_id' => $team->id,
            ]);
        $user->assignRole(RoleDefaults::GOD);

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

        $this->action->execute('admin@test.com', '123456', 'mock-session');

        // Verify permission context is configured
        $this->assertEquals('api', config('permission.defaults.guard'));
        $this->assertEquals($team->id, getPermissionsTeamId());
    }

    #[Test]
    public function it_configures_permission_context_with_fallback_team(): void
    {
        $firstTeam = Team::first(['id']) ?? ModelFactory::createTeam(['name' => 'First Team']);
        $user = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'team_id' => null, // No team assigned
        ]);

        // Ensure GOD role exists
        setPermissionsTeamId($firstTeam->id);
        Role::firstOrCreate(
            [
                'name' => RoleDefaults::GOD,
                'guard_name' => 'api',
                'team_id' => $firstTeam->id,
            ]);
        $user->assignRole(RoleDefaults::GOD);

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

        $this->action->execute('admin@test.com', '123456', 'mock-session');

        // Verify permission context uses first available team
        $this->assertEquals($firstTeam->id, getPermissionsTeamId());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->cognitoService = $this->mock(CognitoAuthService::class);
        $this->action = new VerifyMfaAction($this->cognitoService);
    }
}
