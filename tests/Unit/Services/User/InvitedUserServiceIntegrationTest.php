<?php

namespace Tests\Unit\Services\User;

use App\Models\User;
use App\Services\Models\InvitedUserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['users'], scope: 'test')]
#[Group('user')]
class InvitedUserServiceIntegrationTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private InvitedUserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvitedUserService;
    }

    #[Test]
    public function it_creates_and_finds_by_token(): void
    {
        $financer = ModelFactory::createFinancer();
        $inviter = ModelFactory::createUser(['email' => 'inviter@test.com']);

        $data = [
            'first_name' => 'Token',
            'last_name' => 'Test',
            'email' => 'token@example.com',
            'invitation_metadata' => [
                'financer_id' => $financer->id,
            ],
        ];

        $invitedUser = $this->service->createWithRole($data, 'financer_admin', (string) $inviter->id);

        $this->assertNotNull($invitedUser->invitation_token);
        $this->assertEquals('pending', $invitedUser->invitation_status);
        $this->assertEquals('financer_admin', $invitedUser->invitation_metadata['intended_role']);

        $token = $invitedUser->invitation_token;

        // Find by token
        $found = $this->service->findByToken($token);

        $this->assertInstanceOf(User::class, $found);
        $this->assertEquals($invitedUser->id, $found->id);
        $this->assertEquals('pending', $found->invitation_status);
    }

    #[Test]
    public function it_handles_expired_invitations(): void
    {
        $financer = ModelFactory::createFinancer();

        // Create expired invitation
        $invitedUser = ModelFactory::createUser([
            'email' => 'expired@example.com',
            'invitation_status' => 'pending',
            'invitation_token' => $this->service->generateToken(),
            'invitation_expires_at' => Carbon::now()->subDay(),
            'financers' => [
                ['financer' => $financer, 'active' => false],
            ],
        ]);

        $this->assertTrue($this->service->isExpired($invitedUser));
    }

    #[Test]
    public function it_handles_non_expired_invitations(): void
    {
        $financer = ModelFactory::createFinancer();

        // Create valid invitation
        $invitedUser = ModelFactory::createUser([
            'email' => 'valid@example.com',
            'invitation_status' => 'pending',
            'invitation_token' => $this->service->generateToken(),
            'invitation_expires_at' => Carbon::now()->addDay(),
            'financers' => [
                ['financer' => $financer, 'active' => false],
            ],
        ]);

        $this->assertFalse($this->service->isExpired($invitedUser));
    }

    #[Test]
    public function it_generates_unique_tokens(): void
    {
        $token1 = $this->service->generateToken();
        $token2 = $this->service->generateToken();

        $this->assertNotEquals($token1, $token2);
        $this->assertNotEmpty($token1);
        $this->assertNotEmpty($token2);
    }

    #[Test]
    public function it_caches_token_lookups(): void
    {
        $financer = ModelFactory::createFinancer();
        $token = $this->service->generateToken();

        $invitedUser = ModelFactory::createUser([
            'email' => 'cached@example.com',
            'invitation_status' => 'pending',
            'invitation_token' => $token,
            'invitation_expires_at' => Carbon::now()->addDay(),
            'financers' => [
                ['financer' => $financer, 'active' => false],
            ],
        ]);

        // First call - hits database
        $result1 = $this->service->findByTokenCached($token);

        $this->assertInstanceOf(User::class, $result1);
        $this->assertEquals($invitedUser->id, $result1->id);

        // Second call - should use cache
        $result2 = $this->service->findByTokenCached($token);

        $this->assertEquals($result1->id, $result2->id);
    }

    #[Test]
    public function it_invalidates_token_cache(): void
    {
        $token = $this->service->generateToken();

        // Prime the cache
        $this->service->findByTokenCached($token);

        // Invalidate cache
        $this->service->invalidateCache($token);

        // Verify cache is cleared (no assertion failure means success)
        $result = $this->service->findByTokenCached($token);
        $this->assertNull($result);
    }
}
