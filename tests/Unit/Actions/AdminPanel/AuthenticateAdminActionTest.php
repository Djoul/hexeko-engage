<?php

namespace Tests\Unit\Actions\AdminPanel;

use App\Actions\AdminPanel\AuthenticateAdminAction;
use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\TokenResponseDTO;
use App\Enums\IDP\RoleDefaults;
use App\Exceptions\AuthenticationException;
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
class AuthenticateAdminActionTest extends TestCase
{
    use DatabaseTransactions;

    private AuthenticateAdminAction $action;

    private MockInterface $cognitoService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a default team for database queries
        ModelFactory::createTeam(['name' => 'Default Admin Team']);

        // Mock Cognito service only - no DB
        $this->cognitoService = Mockery::mock(CognitoAuthService::class);
        $this->action = new AuthenticateAdminAction($this->cognitoService);
    }

    #[Test]
    public function it_authenticates_admin_user_with_god_role(): void
    {
        $email = 'admin@test.com';
        $password = 'TestPassword123!';

        // Create a mock user with GOD role
        $admin = $this->createMockUser(1, $email, true);

        // Mock successful Cognito authentication
        $this->cognitoService
            ->shouldReceive('authenticate')
            ->once()
            ->with($email, $password)
            ->andReturn([
                'success' => true,
                'access_token' => 'access-token',
                'id_token' => 'id-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
            ]);

        // Mock getting user from token
        $this->cognitoService
            ->shouldReceive('getUserFromToken')
            ->once()
            ->with('access-token')
            ->andReturn($admin);

        $dto = LoginDTO::from([
            'email' => $email,
            'password' => $password,
        ]);

        $result = $this->action->execute($dto);

        $this->assertInstanceOf(TokenResponseDTO::class, $result);
        $this->assertEquals('access-token', $result->accessToken);
        $this->assertEquals('id-token', $result->idToken);
        $this->assertEquals('refresh-token', $result->refreshToken);
        $this->assertEquals(3600, $result->expiresIn);
        $this->assertEquals($email, $result->user['email']);
        $this->assertContains(RoleDefaults::GOD, $result->user['roles']->toArray());
    }

    #[Test]
    public function it_denies_access_to_non_god_users(): void
    {
        $email = 'regular@test.com';
        $password = 'TestPassword123!';

        // Create a mock user WITHOUT GOD role
        $user = $this->createMockUser(2, $email, false);

        // Mock successful Cognito authentication
        $this->cognitoService
            ->shouldReceive('authenticate')
            ->once()
            ->with($email, $password)
            ->andReturn([
                'success' => true,
                'access_token' => 'access-token',
                'id_token' => 'id-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
            ]);

        // Mock getting user from token
        $this->cognitoService
            ->shouldReceive('getUserFromToken')
            ->once()
            ->with('access-token')
            ->andReturn($user);

        $dto = LoginDTO::from([
            'email' => $email,
            'password' => $password,
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Access denied. Admin panel is restricted to GOD users only.');

        $this->action->execute($dto);
    }

    #[Test]
    public function it_handles_invalid_credentials(): void
    {
        $email = 'admin@test.com';
        $password = 'WrongPassword';

        // Mock Cognito authentication failure
        $this->cognitoService
            ->shouldReceive('authenticate')
            ->once()
            ->with($email, $password)
            ->andReturn([
                'success' => false,
                'error' => 'Invalid credentials',
            ]);

        $dto = LoginDTO::from([
            'email' => $email,
            'password' => $password,
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $this->action->execute($dto);
    }

    #[Test]
    public function it_handles_user_not_found_in_database(): void
    {
        $email = 'unknown@test.com';
        $password = 'TestPassword123!';

        // Mock successful Cognito authentication
        $this->cognitoService
            ->shouldReceive('authenticate')
            ->once()
            ->with($email, $password)
            ->andReturn([
                'success' => true,
                'access_token' => 'access-token',
                'id_token' => 'id-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
            ]);

        // Mock getUserFromToken returns null
        $this->cognitoService
            ->shouldReceive('getUserFromToken')
            ->once()
            ->with('access-token')
            ->andReturn(null);

        $dto = LoginDTO::from([
            'email' => $email,
            'password' => $password,
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('User not found in application database');

        $this->action->execute($dto);
    }

    #[Test]
    public function it_handles_cognito_service_exceptions(): void
    {
        $email = 'admin@test.com';
        $password = 'TestPassword123!';

        // Mock Cognito service throwing an exception
        $this->cognitoService
            ->shouldReceive('authenticate')
            ->once()
            ->with($email, $password)
            ->andThrow(new Exception('AWS service unavailable'));

        $dto = LoginDTO::from([
            'email' => $email,
            'password' => $password,
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Authentication failed: AWS service unavailable');

        $this->action->execute($dto);
    }

    #[Test]
    public function it_includes_user_permissions_in_response(): void
    {
        $email = 'admin@test.com';
        $password = 'TestPassword123!';

        // Create a mock user with GOD role and permissions
        $admin = $this->createMockUser(1, $email, true);

        // Mock Cognito authentication
        $this->cognitoService
            ->shouldReceive('authenticate')
            ->once()
            ->with($email, $password)
            ->andReturn([
                'success' => true,
                'access_token' => 'access-token',
                'id_token' => 'id-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
            ]);

        $this->cognitoService
            ->shouldReceive('getUserFromToken')
            ->once()
            ->with('access-token')
            ->andReturn($admin);

        $dto = LoginDTO::from([
            'email' => $email,
            'password' => $password,
        ]);

        $result = $this->action->execute($dto);

        $this->assertInstanceOf(TokenResponseDTO::class, $result);
        $this->assertArrayHasKey('permissions', $result->user);
        $this->assertIsArray($result->user['permissions']->toArray());
    }

    /**
     * Helper method to create a mock user
     */
    private function createMockUser(int $id, string $email, bool $hasGodRole): MockInterface
    {
        $user = Mockery::mock(User::class)->makePartial();

        $user->shouldReceive('hasRole')
            ->with(RoleDefaults::GOD, 'api')
            ->andReturn($hasGodRole);

        $user->shouldReceive('getRoleNames')
            ->andReturn(collect($hasGodRole ? [RoleDefaults::GOD] : [RoleDefaults::BENEFICIARY]));

        $user->shouldReceive('getAllPermissions')
            ->andReturn(collect([
                (object) ['name' => 'admin.access'],
                (object) ['name' => 'admin.manage'],
            ]));

        $user->shouldReceive('getAttribute')->andReturnUsing(function ($key) use ($id, $email): int|string|null {
            return match ($key) {
                'id' => $id,
                'email' => $email,
                'first_name' => 'Test',
                'last_name' => 'User',
                default => null
            };
        });

        $user->shouldReceive('setAttribute')->andReturnSelf();
        $user->shouldReceive('__get')->andReturnUsing(function ($key) use ($id, $email): int|string|null {
            return match ($key) {
                'id' => $id,
                'email' => $email,
                'first_name' => 'Test',
                'last_name' => 'User',
                default => null
            };
        });

        return $user;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
