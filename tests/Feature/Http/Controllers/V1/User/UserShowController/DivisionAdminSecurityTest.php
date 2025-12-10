<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\User\UserShowController;

use App\Actions\User\Roles\UserSyncRolesAction;
use App\Enums\IDP\RoleDefaults;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

/**
 * Security tests for DIVISION_ADMIN role to prevent IDOR vulnerabilities
 *
 * This test suite verifies that DIVISION_ADMIN users can only access user data
 * from their own division(s), preventing cross-division Insecure Direct Object Reference attacks.
 */
#[Group('user')]
#[Group('security')]
#[Group('division')]
class DivisionAdminSecurityTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private Division $divisionA;

    private Division $divisionB;

    private Financer $financerA1;

    private Financer $financerA2;

    private Financer $financerB1;

    private User $divisionAdminA;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup two separate divisions
        /** @var Division $divisionA */
        $divisionA = Division::factory()->create(['name' => 'Division A']);
        $this->divisionA = $divisionA;

        /** @var Division $divisionB */
        $divisionB = Division::factory()->create(['name' => 'Division B']);
        $this->divisionB = $divisionB;

        // Create financers in each division
        /** @var Financer $financerA1 */
        $financerA1 = Financer::factory()->create([
            'name' => 'Financer A1',
            'division_id' => $this->divisionA->id,
        ]);
        $this->financerA1 = $financerA1;

        /** @var Financer $financerA2 */
        $financerA2 = Financer::factory()->create([
            'name' => 'Financer A2',
            'division_id' => $this->divisionA->id,
        ]);
        $this->financerA2 = $financerA2;

        /** @var Financer $financerB1 */
        $financerB1 = Financer::factory()->create([
            'name' => 'Financer B1',
            'division_id' => $this->divisionB->id,
        ]);
        $this->financerB1 = $financerB1;

        // Create DIVISION_ADMIN for Division A
        $this->divisionAdminA = $this->createAuthUser(RoleDefaults::DIVISION_ADMIN);
        $this->divisionAdminA->financers()->attach($this->financerA1->id, ['active' => true]);
    }

    #[Test]
    public function division_admin_cannot_view_user_from_different_division(): void
    {
        // Arrange - Create user in Division B
        $userInDivisionB = User::factory()->create();
        $userInDivisionB->financers()->attach($this->financerB1->id, [
            'active' => true,
            'sirh_id' => 'USER-B-001',
            'from' => now()->subYear(),
            'to' => null,
            'role' => 'beneficiary',
        ]);

        // Act - DIVISION_ADMIN from Division A tries to access user from Division B
        $response = $this->actingAs($this->divisionAdminA)
            ->getJson("/api/v1/users/{$userInDivisionB->id}");

        // Assert - Should return 403 Forbidden (IDOR prevention)
        $response->assertForbidden()
            ->assertJson([
                'message' => 'You do not have permission to view this user',
            ]);
    }

    #[Test]
    public function division_admin_can_view_user_from_same_division_but_different_financer(): void
    {
        // Arrange - Create user in Division A but attached to a DIFFERENT financer
        $userInDivisionA = User::factory()->create();
        $userInDivisionA->financers()->attach($this->financerA2->id, [
            'active' => true,
            'sirh_id' => 'USER-A-002',
            'from' => now()->subYear(),
            'to' => null,
            'role' => 'beneficiary',
        ]);

        // Act - DIVISION_ADMIN from Division A accesses user from same division
        $response = $this->actingAs($this->divisionAdminA)
            ->getJson("/api/v1/users/{$userInDivisionA->id}");

        // Assert - Should succeed (division-level access)
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $userInDivisionA->id,
                ],
            ]);
    }

    #[Test]
    public function division_admin_cannot_update_user_from_different_division(): void
    {
        // Arrange - Create user in Division B
        $userInDivisionB = User::factory()->create();
        $userInDivisionB->financers()->attach($this->financerB1->id, [
            'active' => true,
            'sirh_id' => 'USER-B-003',
            'from' => now()->subYear(),
            'to' => null,
            'role' => 'beneficiary',
        ]);

        // Act - Try to update user from different division
        $response = $this->actingAs($this->divisionAdminA)
            ->putJson("/api/v1/users/{$userInDivisionB->id}", [
                'first_name' => 'Hacked Name',
            ]);

        // Assert - Should return 403 Forbidden
        $response->assertForbidden()
            ->assertJson([
                'message' => 'You do not have permission to update this user',
            ]);
    }

    #[Test]
    public function division_admin_can_update_user_from_same_division(): void
    {
        // Arrange - Create user in Division A
        $userInDivisionA = User::factory()->create();
        $userInDivisionA->financers()->attach($this->financerA2->id, [
            'active' => true,
            'sirh_id' => 'USER-A-004',
            'from' => now()->subYear(),
            'to' => null,
            'role' => 'beneficiary',
        ]);

        // Act - Update user from same division
        $response = $this->actingAs($this->divisionAdminA)
            ->putJson("/api/v1/users/{$userInDivisionA->id}", [
                'first_name' => 'Updated Name',
            ]);

        // Assert - Should succeed
        $response->assertOk()
            ->assertJsonPath('data.first_name', 'Updated Name');
    }

    #[Test]
    public function god_role_bypasses_division_isolation(): void
    {
        // Arrange - Create GOD user
        $godUser = $this->createAuthUser(RoleDefaults::GOD);

        // Create user in Division B (different from GOD's scope)
        $userInDivisionB = User::factory()->create();
        $userInDivisionB->financers()->attach($this->financerB1->id, [
            'active' => true,
            'sirh_id' => 'USER-B-005',
            'from' => now()->subYear(),
            'to' => null,
            'role' => 'beneficiary',
        ]);

        // Act - GOD accesses user from any division
        $response = $this->actingAs($godUser)
            ->getJson("/api/v1/users/{$userInDivisionB->id}");

        // Assert - GOD bypasses division isolation
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $userInDivisionB->id,
                ],
            ]);
    }

    #[Test]
    public function division_admin_without_active_financer_cannot_access_any_user(): void
    {
        // Arrange - Create DIVISION_ADMIN without any active financer
        $adminWithoutFinancer = $this->createAuthUser(RoleDefaults::DIVISION_ADMIN);

        // Create user in Division A
        $userInDivisionA = User::factory()->create();
        $userInDivisionA->financers()->attach($this->financerA1->id, [
            'active' => true,
            'sirh_id' => 'USER-A-006',
            'from' => now()->subYear(),
            'to' => null,
            'role' => 'beneficiary',
        ]);

        // Act - Admin without financer tries to access user
        $response = $this->actingAs($adminWithoutFinancer)
            ->getJson("/api/v1/users/{$userInDivisionA->id}");

        // Assert - Should return 403 (no division scope)
        $response->assertForbidden();
    }

    #[Test]
    public function division_admin_can_access_user_with_multiple_divisions_when_one_matches(): void
    {
        // Arrange - Create user attached to BOTH divisions
        $userMultiDivision = User::factory()->create();
        $userMultiDivision->financers()->attach($this->financerA1->id, [
            'active' => true,
            'sirh_id' => 'USER-MULTI-001',
            'from' => now()->subYear(),
            'to' => null,
            'role' => 'beneficiary',
        ]);
        $userMultiDivision->financers()->attach($this->financerB1->id, [
            'active' => true,
            'sirh_id' => 'USER-MULTI-002',
            'from' => now()->subYear(),
            'to' => null,
            'role' => 'beneficiary',
        ]);

        // Act - DIVISION_ADMIN from Division A accesses user
        $response = $this->actingAs($this->divisionAdminA)
            ->getJson("/api/v1/users/{$userMultiDivision->id}");

        // Assert - Should succeed (shares at least one division)
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $userMultiDivision->id,
                ],
            ]);
    }

    #[Test]
    public function division_admin_blocked_when_financer_attachment_is_inactive(): void
    {
        // Arrange - Create user in Division A but with INACTIVE attachment
        $userInDivisionA = User::factory()->create();
        $userInDivisionA->financers()->attach($this->financerA2->id, [
            'active' => false, // INACTIVE
            'sirh_id' => 'USER-A-INACTIVE',
            'from' => now()->subYear(),
            'to' => now()->subMonth(),
            'role' => 'beneficiary',
        ]);

        // Act - Try to access user with inactive financer
        $response = $this->actingAs($this->divisionAdminA)
            ->getJson("/api/v1/users/{$userInDivisionA->id}");

        // Assert - Should block access (inactive attachment)
        $response->assertForbidden();
    }

    #[Test]
    public function division_admin_should_not_be_attached_to_all_division_financers(): void
    {
        // Arrange - Create division with multiple financers
        /** @var Division $division */
        $division = Division::factory()->create(['name' => 'Division Test']);
        /** @var Financer $financer1 */
        $financer1 = Financer::factory()->create([
            'name' => 'Financer 1',
            'division_id' => $division->id,
        ]);
        /** @var Financer $financer2 */
        $financer2 = Financer::factory()->create([
            'name' => 'Financer 2',
            'division_id' => $division->id,
        ]);
        /** @var Financer $financer3 */
        $financer3 = Financer::factory()->create([
            'name' => 'Financer 3',
            'division_id' => $division->id,
        ]);

        // Create admin user WITHOUT default financer, attached ONLY to financer1
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->financers()->attach($financer1->id, [
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
            'from' => now(),
        ]);

        // Set permissions team context
        if ($admin->team_id) {
            setPermissionsTeamId($admin->team_id);
        }

        // Create DIVISION_ADMIN role if it doesn't exist
        Role::firstOrCreate(
            ['name' => RoleDefaults::DIVISION_ADMIN, 'team_id' => $admin->team_id],
            ['guard_name' => 'api']
        );

        // Simulate context with financer_id
        Context::add('financer_id', (string) $financer1->id);

        // Act - Assign DIVISION_ADMIN role
        $action = new UserSyncRolesAction;
        $action->execute($admin, RoleDefaults::DIVISION_ADMIN);

        // Assert - Admin should ONLY be attached to financer1, NOT all division financers
        $admin->refresh();

        // BUG REGRESSION TEST: Should have only 1 financer (financer1)
        $this->assertCount(1, $admin->financers,
            'Division Admin should only be attached to their original financer, not all division financers'
        );

        // Should be attached to financer1
        $this->assertTrue($admin->financers->contains($financer1));

        // Should NOT be attached to financer2 and financer3
        $this->assertFalse($admin->financers->contains($financer2),
            'Division Admin should not be automatically attached to other division financers'
        );
        $this->assertFalse($admin->financers->contains($financer3),
            'Division Admin should not be automatically attached to other division financers'
        );

        // BUT should still be able to VIEW users from other financers via Policy
        $userInFinancer2 = User::factory()->create();
        $userInFinancer2->financers()->attach($financer2->id, [
            'active' => true,
            'sirh_id' => 'USER-F2-001',
            'from' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/users/{$userInFinancer2->id}");

        // Division Admin can still VIEW users from same division (via Policy)
        $response->assertOk();
    }
}
