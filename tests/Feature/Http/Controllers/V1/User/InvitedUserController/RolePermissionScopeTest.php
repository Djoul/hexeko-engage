<?php

namespace Tests\Feature\Http\Controllers\V1\User\InvitedUserController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('role')]
class RolePermissionScopeTest extends ProtectedRouteTestCase
{
    /**
     * Global roles (GOD, HEXEKO_*, DIVISION_*) should use global permissions
     * Financer roles (FINANCER_*) should use financer-specific permissions
     */
    #[Test]
    public function god_has_global_permissions_without_financer_context(): void
    {
        $god = $this->createGlobalUser(RoleDefaults::GOD, 'god@test.com');

        // Create financer
        $financer = ModelFactory::createFinancer([
            'name' => 'Test Financer',
            'status' => 'active',
        ]);

        // GOD can invite anyone anywhere
        $response = $this->actingAs($god)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $financer->id,
                'email' => 'new_admin@test.com',
                'first_name' => 'New',
                'last_name' => 'Admin',
                'intended_role' => RoleDefaults::HEXEKO_SUPER_ADMIN,
            ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function hexeko_super_admin_has_global_permissions(): void
    {
        $hexekoSuperAdmin = $this->createGlobalUser(RoleDefaults::HEXEKO_SUPER_ADMIN, 'hexeko_sa@test.com');

        // Create financer
        $financer = ModelFactory::createFinancer([
            'name' => 'Test Financer',
            'status' => 'active',
        ]);

        // Can invite division roles
        $response = $this->actingAs($hexekoSuperAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $financer->id,
                'email' => 'new_division_admin@test.com',
                'first_name' => 'Division',
                'last_name' => 'Admin',
                'intended_role' => RoleDefaults::DIVISION_SUPER_ADMIN,
            ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function hexeko_admin_has_global_permissions(): void
    {
        $hexekoAdmin = $this->createGlobalUser(RoleDefaults::HEXEKO_ADMIN, 'hexeko_admin@test.com');

        // Create financer
        $financer = ModelFactory::createFinancer([
            'name' => 'Test Financer',
            'status' => 'active',
        ]);

        // Can invite financer roles
        $response = $this->actingAs($hexekoAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $financer->id,
                'email' => 'new_financer_admin@test.com',
                'first_name' => 'Financer',
                'last_name' => 'Admin',
                'intended_role' => RoleDefaults::FINANCER_SUPER_ADMIN,
            ]);

        $response->assertStatus(201);
    }

    #[Test]
    #[Group('division-permissions')]
    public function division_super_admin_has_division_wide_permissions(): void
    {
        $divisionSuperAdmin = $this->createGlobalUser(RoleDefaults::DIVISION_SUPER_ADMIN, 'division_sa@test.com');

        // Create division and financers
        $division = ModelFactory::createDivision(['name' => 'Test Division']);
        $financer1 = ModelFactory::createFinancer(['division_id' => $division->id, 'name' => 'Financer 1']);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division->id, 'name' => 'Financer 2']);

        // Can invite to any financer in division
        $response1 = $this->actingAs($divisionSuperAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $financer1->id,
                'email' => 'user1@test.com',
                'first_name' => 'User1',
                'last_name' => 'Test',
                'intended_role' => RoleDefaults::FINANCER_ADMIN,
            ]);
        $response1->assertStatus(201);

        $response2 = $this->actingAs($divisionSuperAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $financer2->id,
                'email' => 'user2@test.com',
                'first_name' => 'User2',
                'last_name' => 'Test',
                'intended_role' => RoleDefaults::FINANCER_SUPER_ADMIN,
            ]);
        $response2->assertStatus(201);
    }

    #[Test]
    #[Group('division-permissions')]
    public function division_admin_has_division_wide_permissions(): void
    {
        $divisionAdmin = $this->createGlobalUser(RoleDefaults::DIVISION_ADMIN, 'division_admin@test.com');

        // Create division and financer
        $division = ModelFactory::createDivision(['name' => 'Test Division']);
        $financer = ModelFactory::createFinancer(['division_id' => $division->id, 'name' => 'Test Financer']);

        // Can invite financer roles
        $response = $this->actingAs($divisionAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $financer->id,
                'email' => 'new_financer_admin@test.com',
                'first_name' => 'Financer',
                'last_name' => 'Admin',
                'intended_role' => RoleDefaults::FINANCER_ADMIN,
            ]);

        $response->assertStatus(201);
    }

    #[Test]
    #[Group('financer-permissions')]
    public function financer_super_admin_limited_to_financer_context(): void
    {
        // Create financer
        $financer = ModelFactory::createFinancer(['name' => 'Test Financer']);

        // Create user with financer attachment and role in pivot
        $financerSuperAdmin = ModelFactory::createUser([
            'email' => 'financer_sa@test.com',
            'financers' => [
                [
                    'financer' => $financer,
                    'active' => true,
                    'role' => RoleDefaults::FINANCER_SUPER_ADMIN,
                ],
            ],
        ]);

        setPermissionsTeamId($financerSuperAdmin->team_id);
        $this->createAllRoles($financerSuperAdmin->team_id);
        $financerSuperAdmin->assignRole(RoleDefaults::FINANCER_SUPER_ADMIN);

        // Can invite to their financer
        $response = $this->actingAs($financerSuperAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $financer->id,
                'email' => 'beneficiary@test.com',
                'first_name' => 'Beneficiary',
                'last_name' => 'User',
                'intended_role' => RoleDefaults::BENEFICIARY,
            ]);

        $response->assertStatus(201);

        // Cannot invite higher roles
        $response2 = $this->actingAs($financerSuperAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $financer->id,
                'email' => 'division_admin@test.com',
                'first_name' => 'Division',
                'last_name' => 'Admin',
                'intended_role' => RoleDefaults::DIVISION_ADMIN,
            ]);

        $response2->assertStatus(422);
    }

    #[Test]
    #[Group('financer-permissions')]
    public function financer_admin_limited_to_beneficiary_only(): void
    {
        // Create financer
        $financer = ModelFactory::createFinancer(['name' => 'Test Financer']);

        // Create user with financer attachment and role in pivot
        $financerAdmin = ModelFactory::createUser([
            'email' => 'financer_admin@test.com',
            'financers' => [
                [
                    'financer' => $financer,
                    'active' => true,
                    'role' => RoleDefaults::FINANCER_ADMIN,
                ],
            ],
        ]);

        setPermissionsTeamId($financerAdmin->team_id);
        $this->createAllRoles($financerAdmin->team_id);
        $financerAdmin->assignRole(RoleDefaults::FINANCER_ADMIN);

        // Can invite beneficiary
        $response = $this->actingAs($financerAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $financer->id,
                'email' => 'beneficiary@test.com',
                'first_name' => 'Beneficiary',
                'last_name' => 'User',
                'intended_role' => RoleDefaults::BENEFICIARY,
            ]);

        $response->assertStatus(201);

        // Cannot invite financer_admin
        $response2 = $this->actingAs($financerAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $financer->id,
                'email' => 'another_admin@test.com',
                'first_name' => 'Another',
                'last_name' => 'Admin',
                'intended_role' => RoleDefaults::FINANCER_ADMIN,
            ]);

        $response2->assertStatus(422);
    }

    /**
     * Helper to create user without financer attachment (global role)
     */
    private function createGlobalUser(string $role, string $email)
    {
        $user = ModelFactory::createUser([
            'email' => $email,
        ]); // false = no financer attachment

        setPermissionsTeamId($user->team_id);
        $this->createAllRoles($user->team_id);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Create all necessary roles
     */
    private function createAllRoles($teamId): void
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
