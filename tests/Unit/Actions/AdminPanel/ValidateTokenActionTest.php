<?php

namespace Tests\Unit\Actions\AdminPanel;

use App\Actions\AdminPanel\ValidateTokenAction;
use App\Enums\IDP\RoleDefaults;
use App\Models\User;
use App\Services\CognitoAuthService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('admin-panel')]
#[Group('unit')]
class ValidateTokenActionTest extends TestCase
{
    use DatabaseTransactions;

    private ValidateTokenAction $action;

    private MockInterface $cognitoService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a default team for database queries
        ModelFactory::createTeam(['name' => 'Default Admin Team']);

        // Mock Cognito service only - no DB
        $this->cognitoService = Mockery::mock(CognitoAuthService::class);
        $this->action = new ValidateTokenAction($this->cognitoService);
    }

    #[Test]
    public function it_validates_valid_token(): void
    {
        $validToken = 'valid-bearer-token';

        // Create a mock user with GOD role
        $user = $this->createMockUser(1, 'admin@test.com', true);

        // Mock token validation succeeds
        $this->cognitoService
            ->shouldReceive('validateAccessToken')
            ->once()
            ->with($validToken)
            ->andReturn(true);

        // Mock getUserFromToken
        $this->cognitoService
            ->shouldReceive('getUserFromToken')
            ->once()
            ->with($validToken)
            ->andReturn($user);

        // Mock token expiry
        $expiryTime = Carbon::now()->addHour();
        $this->cognitoService
            ->shouldReceive('getTokenExpiry')
            ->once()
            ->with($validToken)
            ->andReturn($expiryTime);

        $result = $this->action->execute($validToken);

        $this->assertTrue($result['valid']);
        $this->assertEquals(1, $result['user']['id']);
        $this->assertEquals('admin@test.com', $result['user']['email']);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertArrayHasKey('expires_in_minutes', $result);
    }

    #[Test]
    public function it_returns_invalid_for_expired_token(): void
    {
        $expiredToken = 'expired-bearer-token';

        // Mock token validation fails
        $this->cognitoService
            ->shouldReceive('validateAccessToken')
            ->once()
            ->with($expiredToken)
            ->andReturn(false);

        $result = $this->action->execute($expiredToken);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Token expired', $result['error']);
    }

    #[Test]
    public function it_returns_invalid_for_malformed_token(): void
    {
        $malformedToken = 'malformed-token';

        // Mock token validation fails
        $this->cognitoService
            ->shouldReceive('validateAccessToken')
            ->once()
            ->with($malformedToken)
            ->andReturn(false);

        $result = $this->action->execute($malformedToken);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Token expired', $result['error']);
    }

    #[Test]
    public function it_returns_invalid_for_non_god_users(): void
    {
        $validToken = 'valid-token-non-god';

        // Create a mock user WITHOUT GOD role
        $user = $this->createMockUser(2, 'regular@test.com', false);

        // Mock token validation succeeds
        $this->cognitoService
            ->shouldReceive('validateAccessToken')
            ->once()
            ->with($validToken)
            ->andReturn(true);

        // Mock getUserFromToken
        $this->cognitoService
            ->shouldReceive('getUserFromToken')
            ->once()
            ->with($validToken)
            ->andReturn($user);

        $result = $this->action->execute($validToken);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Access denied. Admin panel is restricted to GOD users only.', $result['error']);
    }

    #[Test]
    public function it_includes_remaining_time_in_response(): void
    {
        $validToken = 'valid-bearer-token';

        // Create a mock user with GOD role
        $user = $this->createMockUser(1, 'admin@test.com', true);

        // Mock token validation succeeds
        $this->cognitoService
            ->shouldReceive('validateAccessToken')
            ->once()
            ->with($validToken)
            ->andReturn(true);

        // Mock getUserFromToken
        $this->cognitoService
            ->shouldReceive('getUserFromToken')
            ->once()
            ->with($validToken)
            ->andReturn($user);

        // Mock token expiry - 30 minutes from now
        $expiryTime = Carbon::now()->addMinutes(30);
        $this->cognitoService
            ->shouldReceive('getTokenExpiry')
            ->once()
            ->with($validToken)
            ->andReturn($expiryTime);

        $result = $this->action->execute($validToken);

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('expires_in_minutes', $result);
        $this->assertGreaterThan(29, $result['expires_in_minutes']);
        $this->assertLessThanOrEqual(30, $result['expires_in_minutes']);
    }

    #[Test]
    public function it_returns_user_permissions(): void
    {
        $validToken = 'valid-bearer-token';

        // Create a mock user with GOD role and permissions
        $user = $this->createMockUser(1, 'admin@test.com', true);

        // Mock token validation succeeds
        $this->cognitoService
            ->shouldReceive('validateAccessToken')
            ->once()
            ->with($validToken)
            ->andReturn(true);

        // Mock getUserFromToken
        $this->cognitoService
            ->shouldReceive('getUserFromToken')
            ->once()
            ->with($validToken)
            ->andReturn($user);

        // Mock token expiry
        $expiryTime = Carbon::now()->addHour();
        $this->cognitoService
            ->shouldReceive('getTokenExpiry')
            ->once()
            ->with($validToken)
            ->andReturn($expiryTime);

        $result = $this->action->execute($validToken);

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('permissions', $result);
        $this->assertIsArray($result['permissions']);
    }

    #[Test]
    public function it_handles_user_not_found_in_database(): void
    {
        $validToken = 'valid-token-no-user';

        // Mock token validation succeeds
        $this->cognitoService
            ->shouldReceive('validateAccessToken')
            ->once()
            ->with($validToken)
            ->andReturn(true);

        // Mock getUserFromToken returns null
        $this->cognitoService
            ->shouldReceive('getUserFromToken')
            ->once()
            ->with($validToken)
            ->andReturn(null);

        $result = $this->action->execute($validToken);

        $this->assertFalse($result['valid']);
        $this->assertEquals('User not found in application database', $result['error']);
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
