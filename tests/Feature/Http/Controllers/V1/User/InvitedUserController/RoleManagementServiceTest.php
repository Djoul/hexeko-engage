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

#[Group('role')]
#[Group('auth')]
class RoleManagementServiceTest extends ProtectedRouteTestCase
{
    private $division;

    private $financer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create division
        $this->division = ModelFactory::createDivision([
            'name' => 'Test Division',
            'status' => 'active',
        ]);

        // Create financer
        $this->financer = ModelFactory::createFinancer([
            'division_id' => $this->division->id,
            'name' => 'Test Financer',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function division_super_admin_can_invite_financer_admin_exactly_like_production(): void
    {
        // Create a user exactly like the seeder does
        $team = ModelFactory::createTeam(['name' => 'Global Team']);

        $divisionSuperAdmin = User::factory()->create([
            'first_name' => 'Division Super Admin',
            'last_name' => 'User',
            'email' => 'division_super_admin.test@hexeko.com',
            'team_id' => $team->id,
        ]);

        // Attach to financer with role in pivot (single role system)
        $this->financer->users()->attach($divisionSuperAdmin->id, [
            'active' => true,
            'from' => now(),
            'role' => RoleDefaults::DIVISION_SUPER_ADMIN, // Single role
        ]);

        // Set permissions team ID
        setPermissionsTeamId($divisionSuperAdmin->team_id);

        // Create the role
        $role = Role::firstOrCreate(
            [
                'name' => RoleDefaults::DIVISION_SUPER_ADMIN,
                'team_id' => $team->id,
                'guard_name' => 'api',
            ]
        );

        // Create all other roles that might be needed
        $allRoles = [
            RoleDefaults::FINANCER_ADMIN,
            RoleDefaults::FINANCER_SUPER_ADMIN,
            RoleDefaults::BENEFICIARY,
        ];

        foreach ($allRoles as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'team_id' => $team->id,
                'guard_name' => 'api',
            ]);
        }

        // Assign role
        $divisionSuperAdmin->assignRole($role->name);

        // Test the exact scenario that's failing in production
        $response = $this->actingAs($divisionSuperAdmin)
            ->postJson('/api/v1/invited-users', [
                'financer_id' => $this->financer->id,
                'email' => 'jean@boom.be',
                'phone' => '0476565432',
                'first_name' => 'peux plus',
                'last_name' => 'boom',
                'intended_role' => 'financer_admin',
            ]);

        if ($response->status() !== 201) {
            Log::error('Production scenario test failed', [
                'status' => $response->status(),
                'response' => $response->json(),
                'user_global_roles' => $divisionSuperAdmin->roles->pluck('name')->toArray(),
                'user_financer_pivot_roles' => $divisionSuperAdmin->financers->first()?->pivot->roles ?? [],
                'active_financer_id' => activeFinancerID($divisionSuperAdmin),
            ]);
        }

        $response->assertStatus(201);

        // Verify the invited user was created (User with invitation_status='pending')
        $this->assertDatabaseHas('users', [
            'email' => 'jean@boom.be',
            'invitation_status' => 'pending',
        ]);

        // Verify the financer relationship exists
        $invitedUser = User::where('email', 'jean@boom.be')
            ->where('invitation_status', 'pending')
            ->first();
        $this->assertNotNull($invitedUser);
        $this->assertTrue($invitedUser->financers()->where('financer_id', $this->financer->id)->exists());
    }

    #[Test]
    public function test_role_management_service_directly(): void
    {
        // Create division super admin exactly like production
        $team = ModelFactory::createTeam(['name' => 'Global Team']);

        $divisionSuperAdmin = User::factory()->create([
            'email' => 'division_super_admin_direct@test.com',
            'team_id' => $team->id,
        ]);

        setPermissionsTeamId($team->id);

        // Create and assign role
        Role::firstOrCreate([
            'name' => RoleDefaults::DIVISION_SUPER_ADMIN,
            'team_id' => $team->id,
            'guard_name' => 'api',
        ]);

        $divisionSuperAdmin->assignRole(RoleDefaults::DIVISION_SUPER_ADMIN);

        // Test service directly
        $roleService = app(RoleManagementService::class);

        $canManageFinancerAdmin = $roleService->canManageRole($divisionSuperAdmin, 'financer_admin');
        $canManageFinancerSuperAdmin = $roleService->canManageRole($divisionSuperAdmin, 'financer_super_admin');

        Log::info('Production scenario test', [
            'user_email' => $divisionSuperAdmin->email,
            'user_roles' => $divisionSuperAdmin->roles->pluck('name')->toArray(),
            'can_manage_financer_admin' => $canManageFinancerAdmin,
            'can_manage_financer_super_admin' => $canManageFinancerSuperAdmin,
            'assignable_roles' => $roleService->getRolesUserCanAssign($divisionSuperAdmin),
        ]);

        $this->assertTrue($canManageFinancerAdmin, 'DIVISION_SUPER_ADMIN should be able to manage financer_admin');
        $this->assertTrue($canManageFinancerSuperAdmin, 'DIVISION_SUPER_ADMIN should be able to manage financer_super_admin');
    }
}
