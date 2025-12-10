<?php

namespace Tests\Feature\Http\Controllers\V1\User\InvitedUserController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleManagementService;
use Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class RoleHierarchyRealWorldTest extends ProtectedRouteTestCase
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

    #[Test]
    #[Group('division-roles')]
    public function it_allows_division_super_admin_with_financer_context_to_invite_financer_admin(): void
    {
        // Create user without financer attachment first
        $divisionSuperAdmin = ModelFactory::createUser([
            'email' => 'division_super_admin@test.com',
        ]); // false = no financer attachment

        // Set the permissions team ID
        setPermissionsTeamId($divisionSuperAdmin->team_id);

        // Create the role if it doesn't exist
        if (! Role::where('name', RoleDefaults::DIVISION_SUPER_ADMIN)->where('team_id', $divisionSuperAdmin->team_id)->exists()) {
            Role::create(['name' => RoleDefaults::DIVISION_SUPER_ADMIN, 'guard_name' => 'api', 'team_id' => $divisionSuperAdmin->team_id]);
        }

        // Create all other roles that can be invited
        $allRoles = [
            RoleDefaults::FINANCER_ADMIN,
            RoleDefaults::FINANCER_SUPER_ADMIN,
            RoleDefaults::BENEFICIARY,
        ];

        foreach ($allRoles as $roleName) {
            if (! Role::where('name', $roleName)->where('team_id', $divisionSuperAdmin->team_id)->exists()) {
                Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api', 'team_id' => $divisionSuperAdmin->team_id]);
            }
        }

        // Assign global role
        $divisionSuperAdmin->assignRole(RoleDefaults::DIVISION_SUPER_ADMIN);

        // Now test WITHOUT financer attachment (simulating division_super_admin without active financer)
        $response = $this->actingAs($divisionSuperAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $this->financer->id,
                'email' => 'test_financer_admin@test.com',
                'first_name' => 'Test',
                'last_name' => 'Admin',
                'phone' => '0476565432',
                'intended_role' => 'financer_admin',
            ]);

        $response->assertStatus(201);

        // Verify the invited user was created (User with invitation_status='pending')
        $this->assertDatabaseHas('users', [
            'email' => 'test_financer_admin@test.com',
            'invitation_status' => 'pending',
        ]);

        // Verify the financer relationship exists
        $invitedUser = User::where('email', 'test_financer_admin@test.com')
            ->where('invitation_status', 'pending')
            ->first();
        $this->assertNotNull($invitedUser);
        $this->assertTrue($invitedUser->financers()->where('financer_id', $this->financer->id)->exists());
    }

    #[Test]
    #[Group('division-roles')]
    public function it_allows_division_super_admin_with_financer_roles_in_pivot_to_invite_financer_admin(): void
    {
        // Create user WITH financer attachment and roles in pivot
        $divisionSuperAdmin = ModelFactory::createUser([
            'email' => 'division_super_admin_with_financer@test.com',
            'financers' => [
                [
                    'financer' => $this->financer,
                    'active' => true,
                    'role' => RoleDefaults::DIVISION_SUPER_ADMIN, // Single role in pivot
                ],
            ],
        ]);

        // Set the permissions team ID
        setPermissionsTeamId($divisionSuperAdmin->team_id);

        // Create all roles
        $allRoles = [
            RoleDefaults::DIVISION_SUPER_ADMIN,
            RoleDefaults::FINANCER_ADMIN,
            RoleDefaults::FINANCER_SUPER_ADMIN,
            RoleDefaults::BENEFICIARY,
        ];

        foreach ($allRoles as $roleName) {
            if (! Role::where('name', $roleName)->where('team_id', $divisionSuperAdmin->team_id)->exists()) {
                Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api', 'team_id' => $divisionSuperAdmin->team_id]);
            }
        }

        // Also assign global role
        $divisionSuperAdmin->assignRole(RoleDefaults::DIVISION_SUPER_ADMIN);

        // Mock the activeFinancerID to return the financer ID
        app()->bind('activeFinancerID', fn () => $this->financer->id);

        // Test with financer context
        $response = $this->actingAs($divisionSuperAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $this->financer->id,
                'email' => 'test_financer_admin_pivot@test.com',
                'first_name' => 'Test',
                'last_name' => 'Admin',
                'phone' => '0476565432',
                'intended_role' => 'financer_admin',
            ]);

        if ($response->status() !== 201) {
            Log::error('Division super admin with financer roles in pivot test failed', [
                'status' => $response->status(),
                'response' => $response->json(),
                'user_roles' => $divisionSuperAdmin->roles->pluck('name')->toArray(),
                'financer_pivot_roles' => $divisionSuperAdmin->financers->first()?->pivot->roles ?? [],
                'active_financer_id' => activeFinancerID($divisionSuperAdmin),
            ]);
        }

        $response->assertStatus(201);
    }

    #[Test]
    #[Group('debugging')]
    public function it_debugs_role_assignment_logic(): void
    {
        // Create division super admin user
        $divisionSuperAdmin = ModelFactory::createUser([
            'email' => 'debug_division_super_admin@test.com',
        ]);

        setPermissionsTeamId($divisionSuperAdmin->team_id);

        // Create and assign role
        if (! Role::where('name', RoleDefaults::DIVISION_SUPER_ADMIN)->where('team_id', $divisionSuperAdmin->team_id)->exists()) {
            Role::create(['name' => RoleDefaults::DIVISION_SUPER_ADMIN, 'guard_name' => 'api', 'team_id' => $divisionSuperAdmin->team_id]);
        }

        $divisionSuperAdmin->assignRole(RoleDefaults::DIVISION_SUPER_ADMIN);

        // Get role management service
        $roleService = app(RoleManagementService::class);

        // Test canManageRole method directly
        $canManageFinancerAdmin = $roleService->canManageRole($divisionSuperAdmin, 'financer_admin');
        $canManageFinancerSuperAdmin = $roleService->canManageRole($divisionSuperAdmin, 'financer_super_admin');
        $canManageBeneficiary = $roleService->canManageRole($divisionSuperAdmin, 'beneficiary');

        // Get assignable roles
        $assignableRoles = $roleService->getRolesUserCanAssign($divisionSuperAdmin);

        Log::info('Division super admin debug', [
            'user_email' => $divisionSuperAdmin->email,
            'user_global_roles' => $divisionSuperAdmin->roles->pluck('name')->toArray(),
            'active_financer_id' => activeFinancerID($divisionSuperAdmin),
            'can_manage_financer_admin' => $canManageFinancerAdmin,
            'can_manage_financer_super_admin' => $canManageFinancerSuperAdmin,
            'can_manage_beneficiary' => $canManageBeneficiary,
            'assignable_roles' => $assignableRoles,
        ]);

        $this->assertTrue($canManageFinancerAdmin, 'DIVISION_SUPER_ADMIN should be able to manage financer_admin');
        $this->assertTrue($canManageFinancerSuperAdmin, 'DIVISION_SUPER_ADMIN should be able to manage financer_super_admin');
        $this->assertTrue($canManageBeneficiary, 'DIVISION_SUPER_ADMIN should be able to manage beneficiary');
    }
}
