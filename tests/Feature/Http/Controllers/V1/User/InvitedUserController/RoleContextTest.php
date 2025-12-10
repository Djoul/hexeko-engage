<?php

namespace Tests\Feature\Http\Controllers\V1\User\InvitedUserController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('role')]
#[Group('user')]
class RoleContextTest extends ProtectedRouteTestCase
{
    private $division;

    private $financer1;

    private $financer2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create division
        $this->division = ModelFactory::createDivision([
            'name' => 'Test Division',
            'status' => 'active',
        ]);

        // Create two financers in the same division
        $this->financer1 = ModelFactory::createFinancer([
            'division_id' => $this->division->id,
            'name' => 'Test Financer 1',
            'status' => 'active',
        ]);

        $this->financer2 = ModelFactory::createFinancer([
            'division_id' => $this->division->id,
            'name' => 'Test Financer 2',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function division_admin_can_invite_users_to_any_financer_in_division(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, 'division_admin@test.com');

        // Test inviting to financer1
        $response1 = $this->actingAs($divisionAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $this->financer1->id,
                'email' => 'user_for_financer1@test.com',
                'first_name' => 'User1',
                'last_name' => 'Test',
                'intended_role' => RoleDefaults::FINANCER_ADMIN,
            ]);

        $response1->assertStatus(201);

        // Test inviting to financer2
        $response2 = $this->actingAs($divisionAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $this->financer2->id,
                'email' => 'user_for_financer2@test.com',
                'first_name' => 'User2',
                'last_name' => 'Test',
                'intended_role' => RoleDefaults::FINANCER_ADMIN,
            ]);

        $response2->assertStatus(201);
    }

    #[Test]
    public function financer_admin_limited_to_their_financer_when_has_active_financer(): void
    {
        // Create financer admin attached to financer1 only
        $financerAdmin = ModelFactory::createUser([
            'email' => 'financer1_admin@test.com',
            'financers' => [
                [
                    'financer' => $this->financer1,
                    'active' => true,
                    'role' => RoleDefaults::FINANCER_ADMIN,
                ],
            ],
        ]);

        setPermissionsTeamId($financerAdmin->team_id);
        $this->createRoles($financerAdmin->team_id);
        $financerAdmin->assignRole(RoleDefaults::FINANCER_ADMIN);

        // Should be able to invite to their financer
        $response1 = $this->actingAs($financerAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $this->financer1->id,
                'email' => 'new_beneficiary1@test.com',
                'first_name' => 'Beneficiary1',
                'last_name' => 'Test',
                'intended_role' => RoleDefaults::BENEFICIARY,
            ]);

        $response1->assertStatus(201);
    }

    #[Test]
    public function hexeko_admin_can_invite_to_any_financer(): void
    {
        $hexekoAdmin = $this->createUserWithRole(RoleDefaults::HEXEKO_ADMIN, 'hexeko_admin@test.com');

        // Create a financer in a different division
        $otherDivision = ModelFactory::createDivision([
            'name' => 'Other Division',
            'status' => 'active',
        ]);

        $otherFinancer = ModelFactory::createFinancer([
            'division_id' => $otherDivision->id,
            'name' => 'Other Financer',
            'status' => 'active',
        ]);

        // Test inviting to financer1
        $response1 = $this->actingAs($hexekoAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $this->financer1->id,
                'email' => 'user_financer1@test.com',
                'first_name' => 'User1',
                'last_name' => 'Test',
                'intended_role' => RoleDefaults::DIVISION_ADMIN,
            ]);

        $response1->assertStatus(201);

        // Test inviting to other division's financer
        $response2 = $this->actingAs($hexekoAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $otherFinancer->id,
                'email' => 'user_other_financer@test.com',
                'first_name' => 'User2',
                'last_name' => 'Test',
                'intended_role' => RoleDefaults::DIVISION_ADMIN,
            ]);

        $response2->assertStatus(201);
    }

    /**
     * Helper to create user with role
     */
    private function createUserWithRole(string $role, string $email)
    {
        $user = ModelFactory::createUser([
            'email' => $email,
            'financers' => [
                ['financer' => $this->financer1, 'active' => true],
            ],
        ]);

        setPermissionsTeamId($user->team_id);
        $this->createRoles($user->team_id);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Create all necessary roles
     */
    private function createRoles($teamId): void
    {
        $allRoles = [
            RoleDefaults::GOD,
            RoleDefaults::HEXEKO_SUPER_ADMIN,
            RoleDefaults::HEXEKO_ADMIN,
            RoleDefaults::DIVISION_SUPER_ADMIN,
            RoleDefaults::DIVISION_ADMIN,
            RoleDefaults::FINANCER_SUPER_ADMIN,
            RoleDefaults::FINANCER_ADMIN,
            RoleDefaults::BENEFICIARY,
        ];

        foreach ($allRoles as $roleName) {
            if (! Role::where('name', $roleName)->where('team_id', $teamId)->exists()) {
                Role::create(['name' => $roleName, 'guard_name' => 'api', 'team_id' => $teamId]);
            }
        }
    }
}
