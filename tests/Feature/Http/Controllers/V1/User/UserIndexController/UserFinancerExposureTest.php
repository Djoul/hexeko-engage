<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\User\UserIndexController;

use App\Enums\IDP\PermissionDefaults;
use App\Enums\IDP\RoleDefaults;
use App\Models\Financer;
use App\Models\User;
use Context;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

/**
 * Security tests for UserResource financer data exposure vulnerability (UE-737)
 *
 * SECURITY CONTEXT:
 * UserResource currently exposes ALL financers attached to a user without contextual filtering.
 * This allows authenticated users to discover financer affiliations beyond their access scope,
 * constituting an information disclosure vulnerability.
 *
 * EXAMPLE ATTACK SCENARIO:
 * - Attacker authenticates with financer_id=1
 * - Attacker queries user X who belongs to financer_id=1 AND financer_id=2
 * - Response exposes BOTH financers, revealing user X also belongs to financer_id=2
 * - Attacker now knows about financer_id=2's existence and user X's multi-financer relationship
 *
 * EXPECTED BEHAVIOR (not yet implemented):
 * UserResource should only expose financers that are in the intersection of:
 * - Financers the target user belongs to
 * - Financers the authenticated user has access to (via Context::get('accessible_financers'))
 *
 * Exception: /me endpoint should expose all user's own financers (self-introspection)
 */
#[Group('security')]
#[Group('user')]
final class UserFinancerExposureTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private Financer $financer1;

    private Financer $financer2;

    private User $authUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two separate financers (different organizations)
        $this->financer1 = ModelFactory::createFinancer(['name' => 'Financer Alpha']);
        $this->financer2 = ModelFactory::createFinancer(['name' => 'Financer Beta']);

        // Create authenticated user belonging to financer1 ONLY
        $this->authUser = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);
        $this->authUser->financers()->sync([
            $this->financer1->id => ['active' => true],
        ]);

        // Give UPDATE_USER permission to bypass controller permission check
        $this->authUser->givePermissionTo(PermissionDefaults::UPDATE_USER);

        // Refresh to ensure financers are loaded
        $this->authUser->refresh()->load('financers');

        // Set accessible financers context (financer1 only)
        Context::add('accessible_financers', [$this->financer1->id]);
        Context::add('financer_ids', [$this->financer1->id]);
    }

    // ==========================================
    // CRITICAL: PUT /api/v1/users/{id}
    // ==========================================

    #[Test]
    public function it_only_exposes_shared_financers_when_updating_user(): void
    {
        // Arrange: Create target user belonging to financer1 (shared) AND financer2 (not accessible)
        $targetUser = ModelFactory::createUser(['first_name' => 'Target']);
        $targetUser->financers()->sync([
            $this->financer1->id => ['active' => true],
            $this->financer2->id => ['active' => true],
        ]);

        $targetUser->refresh();

        // Act: Update user via PUT endpoint with minimal valid payload
        $response = $this->actingAs($this->authUser)->putJson("/api/v1/users/{$targetUser->id}", [
            'id' => $targetUser->id,
            'first_name' => 'Updated Target',
            'email' => $targetUser->email,
        ]);

        // Assert: Response should ONLY contain financer1 (shared financer)
        $response->assertOk()
            ->assertJsonCount(1, 'data.financers')
            ->assertJsonPath('data.financers.0.id', $this->financer1->id)
            ->assertJsonPath('data.financers.0.name', 'Financer Alpha');

        // Assert: financer2 should NOT be present in response (information disclosure prevention)
        $financerIds = collect($response->json('data.financers'))->pluck('id')->toArray();
        $this->assertNotContains(
            $this->financer2->id,
            $financerIds,
            'SECURITY VIOLATION: financer2 leaked in response - auth user should not see this financer'
        );
    }

    #[Test]
    public function it_hides_all_financers_when_updating_user_with_no_shared_financers(): void
    {
        // Arrange: Create target user belonging ONLY to financer2 (inaccessible)
        $targetUser = ModelFactory::createUser(['first_name' => 'Isolated User']);
        $targetUser->financers()->sync([
            $this->financer2->id => ['active' => true],
        ]);

        // Note: This scenario might 403 depending on authorization policy,
        // but if somehow the update succeeds (e.g., bypass), response should expose ZERO financers

        // Act: Attempt to update user (may fail with 403, which is also acceptable)
        $response = $this->actingAs($this->authUser)->putJson("/api/v1/users/{$targetUser->id}", [
            ...$targetUser->toArray(),
            'first_name' => 'Updated Isolated',
        ]);

        // Assert: Either 403 Forbidden OR empty financers array
        if ($response->status() === 200) {
            $response->assertJsonCount(0, 'data.financers');
        } else {
            $response->assertForbidden();
        }
    }

    #[Test]
    public function it_filters_inactive_financer_relationships_when_updating_user(): void
    {
        // Arrange: Target user has financer1 (active) and financer2 (inactive)
        $targetUser = ModelFactory::createUser(['first_name' => 'Target']);
        $targetUser->financers()->sync([
            $this->financer1->id => ['active' => true],
            $this->financer2->id => ['active' => false],  // Inactive relationship
        ]);

        $targetUser->refresh();

        // Act with minimal valid payload
        $response = $this->actingAs($this->authUser)->putJson("/api/v1/users/{$targetUser->id}", [
            'id' => $targetUser->id,
            'first_name' => 'Updated',
            'email' => $targetUser->email,
        ]);

        // Assert: Should only show financer1 (active), not financer2 (inactive)
        $response->assertOk()
            ->assertJsonCount(1, 'data.financers')
            ->assertJsonPath('data.financers.0.id', $this->financer1->id);

        $financerIds = collect($response->json('data.financers'))->pluck('id')->toArray();
        $this->assertNotContains($this->financer2->id, $financerIds, 'Inactive financers should not be exposed');
    }

    // ==========================================
    // CRITICAL: GET /api/v1/users/{id}
    // ==========================================

    #[Test]
    public function it_only_exposes_shared_financers_when_showing_user(): void
    {
        // Arrange: Target user belongs to financer1 (accessible) and financer2 (inaccessible)
        $targetUser = ModelFactory::createUser(['first_name' => 'Target']);
        $targetUser->financers()->sync([
            $this->financer1->id => ['active' => true],
            $this->financer2->id => ['active' => true],
        ]);

        // Act: GET user details
        $response = $this->actingAs($this->authUser)->getJson("/api/v1/users/{$targetUser->id}");

        // Assert: Only financer1 should be visible
        $response->assertOk()
            ->assertJsonCount(1, 'data.financers')
            ->assertJsonPath('data.financers.0.id', $this->financer1->id);

        $financerIds = collect($response->json('data.financers'))->pluck('id')->toArray();
        $this->assertNotContains(
            $this->financer2->id,
            $financerIds,
            'SECURITY VIOLATION: financer2 disclosed via GET endpoint'
        );
    }

    #[Test]
    public function it_exposes_zero_financers_when_showing_user_with_no_shared_financers(): void
    {
        // Arrange: Target user belongs ONLY to financer2
        $targetUser = ModelFactory::createUser(['first_name' => 'Isolated']);
        $targetUser->financers()->sync([
            $this->financer2->id => ['active' => true],
        ]);

        // Act
        $response = $this->actingAs($this->authUser)->getJson("/api/v1/users/{$targetUser->id}");

        // Assert: Either 403 OR empty financers
        if ($response->status() === 200) {
            $response->assertJsonCount(0, 'data.financers');
        } else {
            $response->assertForbidden();
        }
    }

    // ==========================================
    // CRITICAL: GET /api/v1/users (Index)
    // ==========================================

    #[Test]
    public function it_filters_financers_for_all_users_in_index_endpoint(): void
    {
        // Arrange: Create multiple users with different financer configurations
        $user1 = ModelFactory::createUser(['email' => 'user1@test.com']);
        $user1->financers()->sync([
            $this->financer1->id => ['active' => true],
        ]);

        $user2 = ModelFactory::createUser(['email' => 'user2@test.com']);
        $user2->financers()->sync([
            $this->financer1->id => ['active' => true],
            $this->financer2->id => ['active' => true],  // Should be hidden
        ]);

        $user3 = ModelFactory::createUser(['email' => 'user3@test.com']);
        $user3->financers()->sync([
            $this->financer2->id => ['active' => true],  // Inaccessible - user may not appear at all
        ]);

        // Act: Request user list
        $response = $this->actingAs($this->authUser)->getJson('/api/v1/users?per_page=50');

        // Assert: Check that financer2 is NEVER exposed in any user record
        $response->assertOk();
        $users = $response->json('data');

        foreach ($users as $user) {
            $financerIds = collect($user['financers'] ?? [])->pluck('id')->toArray();

            $this->assertNotContains(
                $this->financer2->id,
                $financerIds,
                "SECURITY VIOLATION: financer2 leaked in user list for user {$user['id']}"
            );
        }

        // Optional: Verify user1 shows financer1
        $user1Data = collect($users)->firstWhere('id', $user1->id);
        if ($user1Data) {
            $user1FinancerIds = collect($user1Data['financers'] ?? [])->pluck('id')->toArray();
            $this->assertContains($this->financer1->id, $user1FinancerIds, 'User1 should show financer1');
        }

        // Optional: Verify user2 shows ONLY financer1 (not financer2)
        $user2Data = collect($users)->firstWhere('id', $user2->id);
        if ($user2Data) {
            $this->assertCount(1, $user2Data['financers'] ?? [], 'User2 should show only 1 financer');
            $this->assertEquals($this->financer1->id, $user2Data['financers'][0]['id'] ?? null);
        }
    }

    #[Test]
    public function it_maintains_financer_filtering_across_pagination(): void
    {
        // Arrange: Create 30 users with mixed financer access
        for ($i = 0; $i < 30; $i++) {
            $user = ModelFactory::createUser(['email' => "bulk{$i}@test.com"]);
            $user->financers()->sync([
                $this->financer1->id => ['active' => true],
                $this->financer2->id => ['active' => true],  // Should never be exposed
            ]);
        }

        // Act: Request paginated results
        $page1 = $this->actingAs($this->authUser)->getJson('/api/v1/users?per_page=10&pagination=page&page=1');
        $page2 = $this->actingAs($this->authUser)->getJson('/api/v1/users?per_page=10&pagination=page&page=2');

        // Assert: financer2 should NEVER appear across any page
        foreach ([$page1, $page2] as $response) {
            $response->assertOk();
            $users = $response->json('data');

            foreach ($users as $user) {
                $financerIds = collect($user['financers'] ?? [])->pluck('id')->toArray();
                $this->assertNotContains(
                    $this->financer2->id,
                    $financerIds,
                    "SECURITY VIOLATION: financer2 exposed in pagination for user {$user['id']}"
                );
            }
        }
    }

    // ==========================================
    // MEDIUM: GET /api/v1/me
    // ==========================================

    #[Test]
    public function it_exposes_all_own_financers_in_me_endpoint(): void
    {
        // Arrange: Auth user belongs to financer1 AND financer2
        $this->authUser->financers()->sync([
            $this->financer1->id => ['active' => true],
            $this->financer2->id => ['active' => true],
        ]);

        // Act: Request own profile
        $response = $this->actingAs($this->authUser)->getJson('/api/v1/me');

        // Assert: /me endpoint SHOULD expose ALL user's own financers (no filtering for self-introspection)
        $response->assertOk()
            ->assertJsonCount(2, 'data.financers');

        $financerIds = collect($response->json('data.financers'))->pluck('id')->toArray();
        $this->assertContains($this->financer1->id, $financerIds);
        $this->assertContains($this->financer2->id, $financerIds);
    }

    #[Test]
    public function it_may_hide_inactive_financers_in_me_endpoint(): void
    {
        // Arrange: Auth user has financer1 (active) and financer2 (inactive)
        $this->authUser->financers()->sync([
            $this->financer1->id => ['active' => true],
            $this->financer2->id => ['active' => false],
        ]);

        // Act
        $response = $this->actingAs($this->authUser)->getJson('/api/v1/me');

        // Assert: Business decision - should inactive financers be visible in /me?
        // Current expectation: Only active financers shown
        $response->assertOk();

        $financerIds = collect($response->json('data.financers'))->pluck('id')->toArray();

        // If you want to expose inactive financers in /me, change this assertion
        $this->assertContains($this->financer1->id, $financerIds, 'Active financer should be visible');
        $this->assertNotContains(
            $this->financer2->id,
            $financerIds,
            'Inactive financer should be hidden (business rule - discuss if needed)'
        );
    }

    // ==========================================
    // Edge Cases & GOD Role
    // ==========================================

    #[Test]
    public function it_allows_god_role_to_see_all_financers(): void
    {
        // Arrange: Create GOD user
        $godUser = $this->createAuthUser(RoleDefaults::GOD);

        // Create target user with financer2 (outside GOD's accessible_financers)
        $targetUser = ModelFactory::createUser(['first_name' => 'Target']);
        $targetUser->financers()->sync([
            $this->financer2->id => ['active' => true],
        ]);

        // Act: GOD accesses user
        $response = $this->actingAs($godUser)->getJson("/api/v1/users/{$targetUser->id}");

        // Assert: GOD should see all financers (bypass multi-tenant isolation)
        $response->assertOk()
            ->assertJsonCount(1, 'data.financers')
            ->assertJsonPath('data.financers.0.id', $this->financer2->id);
    }

    #[Test]
    public function it_handles_users_with_no_financer_attachments(): void
    {
        // Arrange: Create orphan user with NO financers
        $orphanUser = ModelFactory::createUser(['first_name' => 'Orphan']);
        // Don't attach any financers

        // Act
        $response = $this->actingAs($this->authUser)->getJson("/api/v1/users/{$orphanUser->id}");

        // Assert: Either 403 OR empty financers array
        if ($response->status() === 200) {
            $response->assertJson(['data' => ['financers' => []]]);
        } else {
            $response->assertForbidden();
        }
    }

    #[Test]
    public function it_prevents_financer_discovery_via_search_parameters(): void
    {
        // Arrange: Create users with distinct financers
        $user1 = ModelFactory::createUser(['first_name' => 'Alice']);
        $user1->financers()->sync([$this->financer1->id => ['active' => true]]);

        $user2 = ModelFactory::createUser(['first_name' => 'Bob']);
        $user2->financers()->sync([
            $this->financer1->id => ['active' => true],
            $this->financer2->id => ['active' => true],
        ]);

        // Act: Search for users
        $response = $this->actingAs($this->authUser)->getJson('/api/v1/users?search=Bob');

        // Assert: Bob's record should NOT expose financer2
        $response->assertOk();
        $bobData = collect($response->json('data'))->firstWhere('first_name', 'Bob');

        if ($bobData) {
            $financerIds = collect($bobData['financers'] ?? [])->pluck('id')->toArray();
            $this->assertNotContains(
                $this->financer2->id,
                $financerIds,
                'SECURITY VIOLATION: Search results leak inaccessible financer'
            );
        }
    }
}
