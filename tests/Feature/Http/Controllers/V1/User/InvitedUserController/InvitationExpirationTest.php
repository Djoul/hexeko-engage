<?php

namespace Tests\Feature\Http\Controllers\V1\User\InvitedUserController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Financer;
use App\Models\User;
use App\Services\Models\InvitedUserService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class InvitationExpirationTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create auth user with appropriate role for creating invited users
        $this->auth = $this->createAuthUser(role: RoleDefaults::FINANCER_SUPER_ADMIN, withContext: true, returnDetails: true);
        $this->financer = $this->currentFinancer;

    }

    #[Test]
    public function it_sets_expiration_to_exactly_7_days(): void
    {
        // Use unique email to avoid conflicts in parallel tests
        $uniqueEmail = 'john.doe.'.uniqid().'@example.com';

        // Create invitation
        $response = $this->actingAs($this->auth)->postJson('/api/v1/invited-users', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $uniqueEmail,
            'financer_id' => $this->financer->id,
        ]);

        $response->assertCreated();

        // Get the created invitation (User with invitation_status='pending')
        $invitation = User::where('email', $uniqueEmail)
            ->where('invitation_status', 'pending')
            ->first();

        // Assert expiration is exactly 7 days from invited_at
        $expectedExpiration = Carbon::parse($invitation->invited_at)->addDays(7);
        $this->assertTrue(
            Carbon::parse($invitation->invitation_expires_at)->equalTo($expectedExpiration),
            "Expected expiration to be {$expectedExpiration}, got {$invitation->invitation_expires_at}"
        );
    }

    #[Test]
    public function it_correctly_identifies_expired_invitations(): void
    {
        $invitedUserService = app(InvitedUserService::class);

        // Create an expired invitation
        $expiredInvitation = ModelFactory::createUser([
            'email' => 'expired@test.com',
            'invitation_status' => 'pending',
            'invitation_expires_at' => Carbon::now()->subDay(),
            'invitation_token' => 'expired-token',
            'financers' => [
                [
                    'financer' => $this->financer,
                    'active' => false,
                ],
            ],
        ]);

        // Create a valid invitation
        $validInvitation = ModelFactory::createUser([
            'email' => 'valid@test.com',
            'invitation_status' => 'pending',
            'invitation_expires_at' => Carbon::now()->addDays(3),
            'invitation_token' => 'valid-token',
            'financers' => [
                [
                    'financer' => $this->financer,
                    'active' => false,
                ],
            ],
        ]);

        // Test the isExpired method via InvitedUserService
        $this->assertTrue($invitedUserService->isExpired($expiredInvitation));
        $this->assertFalse($invitedUserService->isExpired($validInvitation));
    }

    #[Test]
    public function it_can_update_expired_invitation_since_no_expiration_check_exists(): void
    {
        // Create an expired invitation (User with invitation_status='pending')
        $invitation = ModelFactory::createUser([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'old@example.com',
            'invitation_status' => 'pending',
            'invitation_expires_at' => Carbon::now()->subDay(),
            'invitation_token' => 'expired-token',
            'financers' => [
                [
                    'financer' => $this->financer,
                    'active' => true, // Must be active for UserPolicy to allow access
                ],
            ],
        ]);

        // Re-hydrate context before API call to ensure correct financer_id
        $this->hydrateAuthorizationContext($this->auth, currentFinancer: $this->financer);

        // Try to update the expired invitation
        $response = $this->actingAs($this->auth)->putJson("/api/v1/users/{$invitation->id}", [
            'id' => $invitation->id, // Required by validation
            'first_name' => 'New',
            'last_name' => 'Name',
            'email' => 'new@example.com',
            'financer_id' => $this->financer->id,
        ]);

        // Currently succeeds even if expired
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'email',
                    'first_name',
                    'last_name',
                ],
            ]);

        // Verify data was changed
        $this->assertDatabaseHas('users', [
            'id' => $invitation->id,
            'first_name' => 'New',
            'last_name' => 'Name',
            'email' => 'new@example.com',
            'invitation_status' => 'pending',
        ]);
    }

    #[Test]
    public function it_returns_all_invitations_including_expired_in_listing(): void
    {
        $initialCount = User::where('invitation_status', 'pending')->count();

        // Create 3 valid invitations
        for ($i = 1; $i <= 3; $i++) {
            ModelFactory::createUser([
                'email' => "valid{$i}@test.com",
                'invitation_status' => 'pending',
                'invitation_expires_at' => Carbon::now()->addDays(3),
                'invitation_token' => fake()->uuid(),
                'financers' => [
                    [
                        'financer' => $this->financer,
                        'active' => false,
                    ],
                ],
            ]);
        }

        // Create 2 expired invitations
        for ($i = 1; $i <= 2; $i++) {
            ModelFactory::createUser([
                'email' => "expired{$i}@test.com",
                'invitation_status' => 'pending',
                'invitation_expires_at' => Carbon::now()->subDays(2),
                'invitation_token' => fake()->uuid(),
                'financers' => [
                    [
                        'financer' => $this->financer,
                        'active' => false,
                    ],
                ],
            ]);
        }

        // Get the listing
        $response = $this->actingAs($this->auth)->getJson('/api/v1/users');

        $response->assertOk();

        // Currently returns all invitations (expired and valid)
        $data = $response->json('data');
        $returnedCount = is_array($data) && ! isset($data['data']) ? count($data) : count($data['data'] ?? []);

        // Should return all 5 new invitations plus any initial ones
        $this->assertGreaterThanOrEqual($initialCount + 5, $returnedCount);
    }

    #[Test]
    public function it_handles_boundary_case_for_exact_7_days(): void
    {
        $invitedUserService = app(InvitedUserService::class);

        // Get initial count of invited users for this financer only
        $initialCount = User::where('invitation_status', 'pending')
            ->whereHas('financers', fn ($q) => $q->where('financers.id', $this->financer->id))
            ->count();

        // Create invitation exactly 7 days old (should be expired)
        $boundaryInvitation = ModelFactory::createUser([
            'email' => 'boundary@test.com',
            'invitation_status' => 'pending',
            'invitation_expires_at' => Carbon::now()->subMinute(),
            'invitation_token' => 'boundary-token',
            'invited_at' => Carbon::now()->subDays(7)->subMinute(),
            'financers' => [
                [
                    'financer' => $this->financer,
                    'active' => true, // Must be active to be visible in API
                ],
            ],
        ]);

        $boundaryInvitation->refresh();

        // Should be expired
        $this->assertTrue($invitedUserService->isExpired($boundaryInvitation));

        // Re-hydrate context before API call to ensure correct financer_id
        $this->hydrateAuthorizationContext($this->auth, currentFinancer: $this->financer);

        // Currently still appears in listing (no filtering)
        $response = $this->actingAs($this->auth)->getJson('/api/v1/users');
        $response->assertOk();

        $data = $response->json('data');
        $invitations = is_array($data) && ! isset($data['data']) ? $data : ($data['data'] ?? []);
        $invitationIds = collect($invitations)->pluck('id')->toArray();

        // Currently the expired invitation is included
        $this->assertContains($boundaryInvitation->id, $invitationIds);

        // Total count should include the boundary invitation (at least)
        $this->assertGreaterThanOrEqual($initialCount + 1, count($invitations));
    }
}
