<?php

namespace Tests\Feature\Http\Controllers\V1\User\InvitedUserController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class RoleHierarchyInvitationTest extends ProtectedRouteTestCase
{
    private $division;

    private $financer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create common test data
        $this->division = ModelFactory::createDivision([
            'name' => 'Test Division',
            'status' => 'active',
        ]);

        $this->financer = ModelFactory::createFinancer([
            'division_id' => $this->division->id,
            'name' => 'Test Financer',
            'status' => 'active',
        ]);
    }

    /**
     * Test matrix for role hierarchy
     * Each test verifies what roles a user with a specific role can invite
     */
    #[Test]
    #[Group('god-role')]
    public function it_allows_god_to_invite_all_roles(): void
    {
        $god = $this->createUserWithRole(RoleDefaults::GOD, 'god@test.com');

        $rolesToTest = [
            RoleDefaults::HEXEKO_SUPER_ADMIN,
            RoleDefaults::HEXEKO_ADMIN,
            RoleDefaults::DIVISION_SUPER_ADMIN,
            RoleDefaults::DIVISION_ADMIN,
            RoleDefaults::FINANCER_SUPER_ADMIN,
            RoleDefaults::FINANCER_ADMIN,
            RoleDefaults::BENEFICIARY,
        ];

        foreach ($rolesToTest as $index => $role) {
            $response = $this->actingAs($god)
                ->postJson('/api/v1/invited-users', [
                    'financer_id' => $this->financer->id,
                    'email' => "god_invites_{$role}_{$index}@test.com",
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'intended_role' => $role,
                ]);

            $response->assertStatus(201);
        }
    }

    #[Test]
    #[Group('hexeko-roles')]
    public function it_allows_hexeko_super_admin_to_invite_appropriate_roles(): void
    {
        $hexekoSuperAdmin = $this->createUserWithRole(RoleDefaults::HEXEKO_SUPER_ADMIN, 'hexeko_super_admin@test.com');

        $allowedRoles = [
            RoleDefaults::HEXEKO_ADMIN,
            RoleDefaults::DIVISION_SUPER_ADMIN,
            RoleDefaults::DIVISION_ADMIN,
            RoleDefaults::FINANCER_SUPER_ADMIN,
            RoleDefaults::FINANCER_ADMIN,
            RoleDefaults::BENEFICIARY,
        ];

        foreach ($allowedRoles as $index => $role) {
            $response = $this->actingAs($hexekoSuperAdmin)
                ->postJson('/api/v1/invited-users', [
                    'financer_id' => $this->financer->id,
                    'email' => "hexeko_sa_invites_{$role}_{$index}@test.com",
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'intended_role' => $role,
                ]);

            $response->assertStatus(201);
        }

        // Test forbidden role
        $response = $this->actingAs($hexekoSuperAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $this->financer->id,
                'email' => 'hexeko_sa_cannot_invite_god@test.com',
                'first_name' => 'Test',
                'last_name' => 'User',
                'intended_role' => RoleDefaults::GOD,
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    #[Group('hexeko-roles')]
    public function it_allows_hexeko_admin_to_invite_appropriate_roles(): void
    {
        $hexekoAdmin = $this->createUserWithRole(RoleDefaults::HEXEKO_ADMIN, 'hexeko_admin@test.com');

        $allowedRoles = [
            RoleDefaults::DIVISION_SUPER_ADMIN,
            RoleDefaults::DIVISION_ADMIN,
            RoleDefaults::FINANCER_SUPER_ADMIN,
            RoleDefaults::FINANCER_ADMIN,
            RoleDefaults::BENEFICIARY,
        ];

        foreach ($allowedRoles as $index => $role) {
            $response = $this->actingAs($hexekoAdmin)
                ->postJson('/api/v1/invited-users', [
                    'financer_id' => $this->financer->id,
                    'email' => "hexeko_admin_invites_{$role}_{$index}@test.com",
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'intended_role' => $role,
                ]);

            $response->assertStatus(201);
        }

        // Test forbidden roles
        $forbiddenRoles = [
            RoleDefaults::GOD,
            RoleDefaults::HEXEKO_SUPER_ADMIN,
            RoleDefaults::HEXEKO_ADMIN,
        ];

        foreach ($forbiddenRoles as $index => $role) {
            $response = $this->actingAs($hexekoAdmin)
                ->postJson('/api/v1/invited-users', [
                    'financer_id' => $this->financer->id,
                    'email' => "hexeko_admin_cannot_{$role}_{$index}@test.com",
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'intended_role' => $role,
                ]);

            $response->assertStatus(422);
        }
    }

    #[Test]
    #[Group('division-roles')]
    public function it_allows_division_super_admin_to_invite_appropriate_roles(): void
    {
        $divisionSuperAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_SUPER_ADMIN, 'division_super_admin@test.com');

        $allowedRoles = [
            RoleDefaults::DIVISION_ADMIN,
            RoleDefaults::FINANCER_SUPER_ADMIN,
            RoleDefaults::FINANCER_ADMIN,
            RoleDefaults::BENEFICIARY,
        ];

        foreach ($allowedRoles as $index => $role) {
            $response = $this->actingAs($divisionSuperAdmin)
                ->postJson('/api/v1/invited-users', [
                    'financer_id' => $this->financer->id,
                    'email' => "div_sa_invites_{$role}_{$index}@test.com",
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'intended_role' => $role,
                ]);

            $response->assertStatus(201);
        }

        // Test forbidden roles
        $forbiddenRoles = [
            RoleDefaults::GOD,
            RoleDefaults::HEXEKO_SUPER_ADMIN,
            RoleDefaults::HEXEKO_ADMIN,
            RoleDefaults::DIVISION_SUPER_ADMIN,
        ];

        foreach ($forbiddenRoles as $index => $role) {
            $response = $this->actingAs($divisionSuperAdmin)
                ->postJson('/api/v1/invited-users', [
                    'financer_id' => $this->financer->id,
                    'email' => "div_sa_cannot_{$role}_{$index}@test.com",
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'intended_role' => $role,
                ]);

            $response->assertStatus(422);
        }
    }

    #[Test]
    #[Group('division-roles')]
    public function it_allows_division_admin_to_invite_appropriate_roles(): void
    {
        $divisionAdmin = $this->createUserWithRole(RoleDefaults::DIVISION_ADMIN, 'division_admin@test.com');

        $allowedRoles = [
            RoleDefaults::FINANCER_SUPER_ADMIN,
            RoleDefaults::FINANCER_ADMIN,
            RoleDefaults::BENEFICIARY,
        ];

        foreach ($allowedRoles as $index => $role) {
            $response = $this->actingAs($divisionAdmin)
                ->postJson('/api/v1/invited-users', [
                    'financer_id' => $this->financer->id,
                    'email' => "div_admin_invites_{$role}_{$index}@test.com",
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'intended_role' => $role,
                ]);

            $response->assertStatus(201);
        }

        // Test forbidden roles
        $forbiddenRoles = [
            RoleDefaults::GOD,
            RoleDefaults::HEXEKO_SUPER_ADMIN,
            RoleDefaults::HEXEKO_ADMIN,
            RoleDefaults::DIVISION_SUPER_ADMIN,
            RoleDefaults::DIVISION_ADMIN,
        ];

        foreach ($forbiddenRoles as $index => $role) {
            $response = $this->actingAs($divisionAdmin)
                ->postJson('/api/v1/invited-users', [
                    'financer_id' => $this->financer->id,
                    'email' => "div_admin_cannot_{$role}_{$index}@test.com",
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'intended_role' => $role,
                ]);

            $response->assertStatus(422);
        }
    }

    #[Test]
    #[Group('financer-roles')]
    public function it_allows_financer_super_admin_to_invite_appropriate_roles(): void
    {
        $financerSuperAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_SUPER_ADMIN, 'financer_super_admin@test.com');

        $allowedRoles = [
            RoleDefaults::FINANCER_ADMIN,
            RoleDefaults::BENEFICIARY,
        ];

        foreach ($allowedRoles as $index => $role) {
            $response = $this->actingAs($financerSuperAdmin)
                ->postJson('/api/v1/invited-users', [
                    'financer_id' => $this->financer->id,
                    'email' => "fin_sa_invites_{$role}_{$index}@test.com",
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'intended_role' => $role,
                ]);

            $response->assertStatus(201);
        }

        // Test forbidden roles
        $forbiddenRoles = [
            RoleDefaults::GOD,
            RoleDefaults::HEXEKO_SUPER_ADMIN,
            RoleDefaults::HEXEKO_ADMIN,
            RoleDefaults::DIVISION_SUPER_ADMIN,
            RoleDefaults::DIVISION_ADMIN,
            RoleDefaults::FINANCER_SUPER_ADMIN,
        ];

        foreach ($forbiddenRoles as $index => $role) {
            $response = $this->actingAs($financerSuperAdmin)
                ->postJson('/api/v1/invited-users', [
                    'financer_id' => $this->financer->id,
                    'email' => "fin_sa_cannot_{$role}_{$index}@test.com",
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'intended_role' => $role,
                ]);

            $response->assertStatus(422);
        }
    }

    #[Test]
    #[Group('financer-roles')]
    public function it_allows_financer_admin_to_invite_only_beneficiary(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, 'financer_admin@test.com');

        // Test allowed role
        $response = $this->actingAs($financerAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $this->financer->id,
                'email' => 'fin_admin_invites_beneficiary@test.com',
                'first_name' => 'Test',
                'last_name' => 'User',
                'intended_role' => RoleDefaults::BENEFICIARY,
            ]);

        $response->assertStatus(201);

        // Test all forbidden roles
        $forbiddenRoles = [
            RoleDefaults::GOD,
            RoleDefaults::HEXEKO_SUPER_ADMIN,
            RoleDefaults::HEXEKO_ADMIN,
            RoleDefaults::DIVISION_SUPER_ADMIN,
            RoleDefaults::DIVISION_ADMIN,
            RoleDefaults::FINANCER_SUPER_ADMIN,
            RoleDefaults::FINANCER_ADMIN,
        ];

        foreach ($forbiddenRoles as $index => $role) {
            $response = $this->actingAs($financerAdmin)
                ->postJson('/api/v1/invited-users', [
                    'financer_id' => $this->financer->id,
                    'email' => "fin_admin_cannot_{$role}_{$index}@test.com",
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'intended_role' => $role,
                ]);

            $response->assertStatus(422);
        }
    }

    #[Test]
    #[Group('beneficiary-role')]
    public function it_prevents_beneficiary_from_inviting_any_role(): void
    {
        $beneficiary = $this->createUserWithRole(RoleDefaults::BENEFICIARY, 'beneficiary@test.com');

        // Test all roles - beneficiary should not be able to invite anyone
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

        foreach ($allRoles as $index => $role) {
            $response = $this->actingAs($beneficiary)
                ->postJson('/api/v1/invited-users', [
                    'financer_id' => $this->financer->id,
                    'email' => "beneficiary_cannot_{$role}_{$index}@test.com",
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'intended_role' => $role,
                ]);

            $response->assertStatus(422);
        }
    }

    #[Test]
    #[Group('error-handling')]
    public function it_provides_clear_error_message_for_unauthorized_role_assignment(): void
    {
        $financerAdmin = $this->createUserWithRole(RoleDefaults::FINANCER_ADMIN, 'financer_admin@test.com');

        $response = $this->actingAs($financerAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $this->financer->id,
                'email' => 'unauthorized_role@test.com',
                'first_name' => 'Test',
                'last_name' => 'User',
                'intended_role' => RoleDefaults::FINANCER_SUPER_ADMIN,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'You are not authorized to assign the role: financer_super_admin',
            ]);
    }

    /**
     * Helper method to create a user with a specific role
     */
    private function createUserWithRole(string $role, string $email)
    {
        $user = ModelFactory::createUser([
            'email' => $email,
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);

        // Set the permissions team ID for spatie permissions package
        setPermissionsTeamId($user->team_id);

        // Ensure the role exists
        if (! Role::where('name', $role)->where('team_id', $user->team_id)->exists()) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'api', 'team_id' => $user->team_id]);
        }

        // Ensure all roles that can be invited exist (for testing purposes)
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
            if (! Role::where('name', $roleName)->where('team_id', $user->team_id)->exists()) {
                Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api', 'team_id' => $user->team_id]);
            }
        }

        $user->assignRole($role);

        return $user;
    }
}
