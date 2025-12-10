<?php

namespace Tests\Unit\Actions\AdminPanel;

use App\Actions\AdminPanel\RefreshTokenAction;
use App\DTOs\Auth\TokenResponseDTO;
use App\Enums\IDP\RoleDefaults;
use App\Exceptions\AuthenticationException;
use App\Models\Team;
use App\Models\User;
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
#[Group('unit')]
class RefreshTokenActionTest extends TestCase
{
    use DatabaseTransactions;

    private RefreshTokenAction $action;

    private MockInterface $cognitoService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a default team for database queries
        ModelFactory::createTeam(['name' => 'Default Admin Team']);

        // Mock Cognito service
        $this->cognitoService = Mockery::mock(CognitoAuthService::class);
        $this->action = new RefreshTokenAction($this->cognitoService);
    }

    #[Test]
    public function it_refreshes_token_successfully(): void
    {
        $refreshToken = 'valid-refresh-token';

        // Create a mock user with GOD role
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')
            ->with(RoleDefaults::GOD, 'api')
            ->andReturn(true);
        $user->shouldReceive('getRoleNames')->andReturn(collect([RoleDefaults::GOD]));
        $user->shouldReceive('getAllPermissions')->andReturn(collect([]));
        $user->shouldReceive('getAttribute')->andReturnUsing(function ($key): int|string|null {
            return match ($key) {
                'id' => 1,
                'email' => 'admin@test.com',
                'first_name' => 'Admin',
                'last_name' => 'User',
                default => null
            };
        });
        $user->shouldReceive('setAttribute')->andReturnSelf();
        $user->shouldReceive('__get')->andReturnUsing(function ($key): int|string|null {
            return match ($key) {
                'id' => 1,
                'email' => 'admin@test.com',
                'first_name' => 'Admin',
                'last_name' => 'User',
                default => null
            };
        });

        // Mock Cognito refresh - returns the correct structure
        $this->cognitoService
            ->shouldReceive('refreshToken')
            ->once()
            ->with($refreshToken)
            ->andReturn([
                'success' => true,
                'access_token' => 'new-access-token',
                'id_token' => 'new-id-token',
                'expires_in' => 3600,
            ]);

        // Mock getUserFromToken - returns a User object
        $this->cognitoService
            ->shouldReceive('getUserFromToken')
            ->once()
            ->with('new-access-token')
            ->andReturn($user);

        $result = $this->action->execute($refreshToken);

        $this->assertInstanceOf(TokenResponseDTO::class, $result);
        $this->assertEquals('new-access-token', $result->accessToken);
        $this->assertEquals('new-id-token', $result->idToken);
        $this->assertEquals($refreshToken, $result->refreshToken); // Same refresh token is kept
        $this->assertEquals(3600, $result->expiresIn);
    }

    #[Test]
    public function it_validates_user_still_has_god_role(): void
    {
        $refreshToken = 'valid-refresh-token';

        // Create a mock user with GOD role
        $admin = Mockery::mock(User::class)->makePartial();
        $admin->shouldReceive('hasRole')
            ->with(RoleDefaults::GOD, 'api')
            ->andReturn(true);
        $admin->shouldReceive('getRoleNames')->andReturn(collect([RoleDefaults::GOD]));
        $admin->shouldReceive('getAllPermissions')->andReturn(collect([]));
        $admin->shouldReceive('getAttribute')->andReturnUsing(function ($key): int|string|null {
            return match ($key) {
                'id' => 1,
                'email' => 'admin@test.com',
                'first_name' => 'Admin',
                'last_name' => 'User',
                default => null
            };
        });
        $admin->shouldReceive('setAttribute')->andReturnSelf();
        $admin->shouldReceive('__get')->andReturnUsing(function ($key): int|string|null {
            return match ($key) {
                'id' => 1,
                'email' => 'admin@test.com',
                'first_name' => 'Admin',
                'last_name' => 'User',
                default => null
            };
        });

        // Mock Cognito refresh
        $this->cognitoService
            ->shouldReceive('refreshToken')
            ->once()
            ->with($refreshToken)
            ->andReturn([
                'success' => true,
                'access_token' => 'new-access-token',
                'id_token' => 'new-id-token',
                'expires_in' => 3600,
            ]);

        // Mock getting user from new token
        $this->cognitoService
            ->shouldReceive('getUserFromToken')
            ->once()
            ->with('new-access-token')
            ->andReturn($admin);

        $result = $this->action->execute($refreshToken);

        $this->assertInstanceOf(TokenResponseDTO::class, $result);
        $this->assertEquals('new-access-token', $result->accessToken);
        $this->assertArrayHasKey('roles', $result->user);
        $this->assertContains(RoleDefaults::GOD, $result->user['roles']->toArray());
    }

    #[Test]
    public function it_denies_refresh_if_user_lost_god_role(): void
    {
        $refreshToken = 'valid-refresh-token-demoted';

        // Create user without GOD role
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')
            ->with(RoleDefaults::GOD, 'api')
            ->andReturn(false); // User doesn't have GOD role
        $user->shouldReceive('getRoleNames')->andReturn(collect(['regular-user']));
        $user->shouldReceive('getAttribute')->andReturnUsing(function ($key): int|string|null {
            return match ($key) {
                'id' => 2,
                'email' => 'demoted@test.com',
                'team_id' => 'de208070-c88c-40cc-914c-20386c771a63',
                default => null
            };
        });
        $user->shouldReceive('setAttribute')->andReturnSelf();
        $user->shouldReceive('__get')->andReturnUsing(function ($key): int|string|null {
            return match ($key) {
                'id' => 2,
                'email' => 'demoted@test.com',
                'team_id' => 'de208070-c88c-40cc-914c-20386c771a63',
                default => null
            };
        });

        // Mock Cognito refresh succeeds
        $this->cognitoService
            ->shouldReceive('refreshToken')
            ->once()
            ->with($refreshToken)
            ->andReturn([
                'success' => true,
                'access_token' => 'new-access-token',
                'id_token' => 'new-id-token',
                'expires_in' => 3600,
            ]);

        // Mock getting user from new token
        $this->cognitoService
            ->shouldReceive('getUserFromToken')
            ->once()
            ->with('new-access-token')
            ->andReturn($user);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Access denied. Admin panel is restricted to GOD users only.');

        $this->action->execute($refreshToken);
    }

    #[Test]
    public function it_handles_invalid_refresh_token(): void
    {
        $invalidToken = 'invalid-refresh-token';

        // Mock Cognito refresh failure
        $this->cognitoService
            ->shouldReceive('refreshToken')
            ->once()
            ->with($invalidToken)
            ->andReturn([
                'success' => false,
                'error' => 'Invalid refresh token',
            ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid refresh token');

        $this->action->execute($invalidToken);
    }

    #[Test]
    public function it_handles_expired_refresh_token(): void
    {
        $expiredToken = 'expired-refresh-token';

        // Mock Cognito refresh failure
        $this->cognitoService
            ->shouldReceive('refreshToken')
            ->once()
            ->with($expiredToken)
            ->andReturn([
                'success' => false,
                'error' => 'Refresh token expired',
            ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Refresh token expired');

        $this->action->execute($expiredToken);
    }

    #[Test]
    public function it_handles_cognito_service_errors(): void
    {
        $refreshToken = 'valid-refresh-token';

        // Mock Cognito service error
        $this->cognitoService
            ->shouldReceive('refreshToken')
            ->once()
            ->with($refreshToken)
            ->andThrow(new Exception('Authentication service unavailable'));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Token refresh failed: Authentication service unavailable');

        $this->action->execute($refreshToken);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
