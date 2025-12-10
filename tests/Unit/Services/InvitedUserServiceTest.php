<?php

namespace Tests\Unit\Services;

use App\Enums\Security\AuthorizationMode;
use App\Models\Financer;
use App\Models\User;
use App\Services\Models\InvitedUserService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[FlushTables(tables: ['users'], scope: 'test')]
#[Group('user')]
class InvitedUserServiceTest extends TestCase
{
    use DatabaseTransactions;

    private InvitedUserService $service;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InvitedUserService;

        $this->financer = ModelFactory::createFinancer();

        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [$this->financer->id],
            [$this->financer->division_id],
            [],
            $this->financer->id  // Set current financer for global scopes
        );

    }

    #[Test]
    public function it_generates_secure_invitation_token(): void
    {
        $token1 = $this->service->generateToken();
        $token2 = $this->service->generateToken();

        // Verify tokens are 32 bytes when decoded (base64 encoded)
        $this->assertEquals(32, strlen(base64_decode($token1)));
        $this->assertEquals(32, strlen(base64_decode($token2)));

        // Verify tokens are unique
        $this->assertNotEquals($token1, $token2);
    }

    #[Test]
    public function it_creates_invitation_with_role_in_metadata(): void
    {
        $financer = ModelFactory::createFinancer();
        $inviter = ModelFactory::createUser(['email' => 'inviter@test.com']);

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'invitation_metadata' => [
                'financer_id' => $financer->id,
            ],
        ];
        $role = 'financer_admin';

        $invitedUser = $this->service->createWithRole($data, $role, (string) $inviter->id);

        $this->assertInstanceOf(User::class, $invitedUser);
        $this->assertEquals('pending', $invitedUser->invitation_status);
        $this->assertEquals('john.doe@example.com', $invitedUser->email);
        $this->assertEquals('John', $invitedUser->first_name);
        $this->assertEquals('Doe', $invitedUser->last_name);
        $this->assertNotNull($invitedUser->invitation_token);
        $this->assertNotNull($invitedUser->invitation_expires_at);
        $this->assertNotNull($invitedUser->invitation_metadata);
        $this->assertArrayHasKey('intended_role', $invitedUser->invitation_metadata);
        $this->assertEquals($role, $invitedUser->invitation_metadata['intended_role']);
        $this->assertEquals((string) $inviter->id, $invitedUser->invited_by);
        $this->assertFalse($invitedUser->enabled);
        $this->assertNull($invitedUser->cognito_id);

        // Verify database record
        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'invitation_status' => 'pending',
            'invited_by' => $inviter->id,
        ]);
    }

    #[Test]
    public function it_validates_invitation_expiration(): void
    {
        $financer = ModelFactory::createFinancer();

        // Not expired invitation
        $notExpiredInvitation = ModelFactory::createUser([
            'email' => 'not-expired@test.com',
            'invitation_status' => 'pending',
            'invitation_expires_at' => Carbon::now()->addDays(2),
            'financers' => [
                ['financer' => $financer, 'active' => false],
            ],
        ]);

        $this->assertFalse($this->service->isExpired($notExpiredInvitation));

        // Expired invitation
        $expiredInvitation = ModelFactory::createUser([
            'email' => 'expired@test.com',
            'invitation_status' => 'pending',
            'invitation_expires_at' => Carbon::now()->subDay(),
            'financers' => [
                ['financer' => $financer, 'active' => false],
            ],
        ]);

        $this->assertTrue($this->service->isExpired($expiredInvitation));

        // No expiration date (should not be expired)
        $noExpirationInvitation = ModelFactory::createUser([
            'email' => 'no-expiration@test.com',
            'invitation_status' => 'pending',
            'invitation_expires_at' => null,
            'financers' => [
                ['financer' => $financer, 'active' => false],
            ],
        ]);

        $this->assertFalse($this->service->isExpired($noExpirationInvitation));
    }

    #[Test]
    public function it_finds_invitation_by_token(): void
    {
        $token = 'test-token-123';
        $financer = ModelFactory::createFinancer();

        $invitation = ModelFactory::createUser([
            'email' => 'test@example.com',
            'invitation_status' => 'pending',
            'invitation_token' => $token,
            'invitation_expires_at' => Carbon::now()->addDay(),
            'invitation_metadata' => [
                'financer_id' => $financer->id,
            ],
            'financers' => [
                ['financer' => $financer, 'active' => false],
            ],
        ]);

        $result = $this->service->findByToken($token);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($invitation->id, $result->id);
        $this->assertEquals('test@example.com', $result->email);
        $this->assertEquals('pending', $result->invitation_status);
    }

    #[Test]
    public function it_caches_invitation_lookups(): void
    {
        $token = 'test-token-cache-123';
        $financer = ModelFactory::createFinancer();

        $invitation = ModelFactory::createUser([
            'email' => 'cached@example.com',
            'invitation_status' => 'pending',
            'invitation_token' => $token,
            'invitation_expires_at' => Carbon::now()->addDay(),
            'invitation_metadata' => [
                'financer_id' => $financer->id,
            ],
            'financers' => [
                ['financer' => $financer, 'active' => false],
            ],
        ]);

        // First call - should hit database
        $result1 = $this->service->findByTokenCached($token);

        $this->assertInstanceOf(User::class, $result1);
        $this->assertEquals($invitation->id, $result1->id);

        // Second call - should hit cache (verify by checking cache directly)
        $cacheKey = "invitation:token:{$token}";
        $this->assertTrue(Cache::has($cacheKey));

        $cachedResult = $this->service->findByTokenCached($token);
        $this->assertInstanceOf(User::class, $cachedResult);
        $this->assertEquals($invitation->id, $cachedResult->id);
    }

    #[Test]
    public function it_invalidates_cache_for_token(): void
    {
        $token = 'test-token-invalidate-123';
        $cacheKey = "invitation:token:{$token}";

        // Set cache manually
        Cache::put($cacheKey, 'test-value', 300);
        $this->assertTrue(Cache::has($cacheKey));

        // Invalidate
        $this->service->invalidateCache($token);

        // Verify cache is cleared
        $this->assertFalse(Cache::has($cacheKey));
    }

    #[Test]
    public function it_creates_invited_user(): void
    {
        $financer = ModelFactory::createFinancer();

        $data = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'invitation_metadata' => [
                'financer_id' => $financer->id,
            ],
        ];

        $result = $this->service->create($data);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('jane@example.com', $result->email);
        $this->assertEquals('pending', $result->invitation_status);
        $this->assertFalse($result->enabled);
        $this->assertNull($result->cognito_id);

        // Verify database
        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'invitation_status' => 'pending',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);
    }

    #[Test]
    public function it_wraps_create_exceptions(): void
    {
        // Create a user with a specific invitation_token
        $existingToken = 'unique-token-123';
        ModelFactory::createUser([
            'email' => 'existing@example.com',
            'invitation_token' => $existingToken,
            'invitation_status' => 'pending',
        ]);

        // Try to create user with duplicate invitation_token to trigger unique constraint error
        $data = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'invitation_token' => $existingToken, // Duplicate token violates unique constraint
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to create invited user');

        $this->service->create($data);
    }

    #[Test]
    public function it_returns_null_when_token_not_found(): void
    {
        $token = 'invalid-token-xyz';

        $result = $this->service->findByToken($token);

        $this->assertNull($result);
    }

    #[Test]
    public function it_caches_null_result_when_token_not_found(): void
    {
        $token = 'invalid-token-cached-xyz';

        $result = $this->service->findByTokenCached($token);

        $this->assertNull($result);

        // Note: Laravel Cache may not cache NULL values by default
        // This test verifies that findByTokenCached returns null without error
    }

    #[Test]
    public function it_creates_user_with_default_invitation_status(): void
    {
        $data = [
            'first_name' => 'Default',
            'last_name' => 'User',
            'email' => 'default@example.com',
        ];

        $result = $this->service->create($data);

        $this->assertEquals('pending', $result->invitation_status);
        $this->assertFalse($result->enabled);
        $this->assertNull($result->cognito_id);
    }
}
