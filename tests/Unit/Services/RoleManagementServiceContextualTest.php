<?php

namespace Tests\Unit\Services;

use App\Enums\IDP\RoleDefaults;
use App\Models\Financer;
use App\Models\User;
use App\Services\RoleManagementService;
use Context;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Str;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
#[Group('role')]
class RoleManagementServiceContextualTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private RoleManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RoleManagementService;

        // Create necessary roles for tests
        $this->createRolesIfNotExist();
    }

    private function createRolesIfNotExist(): void
    {
        $roles = [
            RoleDefaults::BENEFICIARY,
            RoleDefaults::FINANCER_ADMIN,
            RoleDefaults::FINANCER_SUPER_ADMIN,
            RoleDefaults::DIVISION_ADMIN,
            RoleDefaults::DIVISION_SUPER_ADMIN,
        ];

        foreach ($roles as $role) {
            if (! Role::where('name', $role)->where('guard_name', 'api')->exists()) {
                Role::create([
                    'id' => Str::uuid()->toString(),
                    'name' => $role,
                    'guard_name' => 'api',
                ]);
            }
        }
    }

    #[Test]
    public function it_manages_roles_based_on_financer_context(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create();

        // Attach user to financer with financer_admin role in context
        $user->financers()->attach($financer->id, [
            'active' => true,
            'from' => now(),
            'role' => RoleDefaults::FINANCER_ADMIN,
        ]);
        Context::add('financer_id', $financer->id);
        // Financer admin can manage beneficiary role
        $canManage = $this->service->canManageRole($user, RoleDefaults::BENEFICIARY);

        $this->assertTrue($canManage);

        // Financer admin cannot manage division admin role
        $canManage = $this->service->canManageRole($user, RoleDefaults::DIVISION_ADMIN);
        $this->assertFalse($canManage);
    }

    #[Test]
    public function it_returns_assignable_roles_for_financer_context(): void
    {
        $financer = Financer::factory()->create();

        // Create user with team_id (required for Spatie permissions)
        $user = User::factory()->create([
            'team_id' => $financer->id, // Use financer as team
        ]);

        // Attach user to financer with financer_admin role (pivot contains the role)
        $user->financers()->attach($financer->id, [
            'active' => true,
            'from' => now(),
            'role' => RoleDefaults::FINANCER_ADMIN,
        ]);

        // Set permissions team context
        setPermissionsTeamId($financer->id);

        // Sync user's Spatie role from pivot role (required for single-role system)
        $user->syncRoles([RoleDefaults::FINANCER_ADMIN]);

        Context::add('financer_id', $financer->id);
        $assignableRoles = $this->service->getRolesUserCanAssign($user);

        // Financer admin can assign beneficiary
        $this->assertContains(RoleDefaults::BENEFICIARY, $assignableRoles);
        // But not division level roles
        $this->assertNotContains(RoleDefaults::DIVISION_ADMIN, $assignableRoles);
        $this->assertNotContains(RoleDefaults::DIVISION_SUPER_ADMIN, $assignableRoles);
    }

    #[Test]
    public function it_handles_multiple_financer_contexts(): void
    {
        $user = User::factory()->create();
        $financer1 = Financer::factory()->create();
        $financer2 = Financer::factory()->create();

        // User has different roles in different financers
        $user->financers()->attach($financer1->id, [
            'active' => true,
            'from' => now(),
            'role' => RoleDefaults::FINANCER_ADMIN,
        ]);
        $user->financers()->attach($financer2->id, [
            'active' => false,
            'from' => now(),
            'role' => RoleDefaults::BENEFICIARY,
        ]);
        // Set financer1 as active context
        Context::add('financer_id', $financer1->id);
        // In financer1 context, user can manage beneficiary
        $canManage = $this->service->canManageRole($user, RoleDefaults::BENEFICIARY);
        $this->assertTrue($canManage);

        // In financer2 context (where user is only beneficiary), cannot manage others
        $canManage = $this->service->canManageRole($user, RoleDefaults::FINANCER_ADMIN);
        $this->assertFalse($canManage);
    }

    #[Test]
    public function it_handles_beneficiary_role_in_financer_context(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create();

        // Attach user to financer with beneficiary role (lowest in hierarchy)
        $user->financers()->attach($financer->id, [
            'active' => true,
            'from' => now(),
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        Context::add('financer_id', $financer->id);
        // Beneficiary cannot manage other roles
        $canManage = $this->service->canManageRole($user, RoleDefaults::BENEFICIARY);
        $this->assertFalse($canManage);

        // No assignable roles for beneficiary
        $assignableRoles = $this->service->getRolesUserCanAssign($user);
        $this->assertEmpty($assignableRoles);
    }

    #[Test]
    public function it_handles_inactive_financer_context(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create();

        // Attach user to financer but mark as inactive
        $user->financers()->attach($financer->id, [
            'active' => false,
            'from' => now(),
            'role' => RoleDefaults::FINANCER_ADMIN,
        ]);

        // When financer is inactive, cannot manage roles
        $canManage = $this->service->canManageRole($user, RoleDefaults::BENEFICIARY);
        $this->assertFalse($canManage);

        // No assignable roles for inactive financer context
        $assignableRoles = $this->service->getRolesUserCanAssign($user);
        $this->assertEmpty($assignableRoles);
    }

    #[Test]
    public function it_handles_hierarchy_within_financer_context(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create();

        // User has financer_super_admin role in context
        $user->financers()->attach($financer->id, [
            'active' => true,
            'from' => now(),
            'role' => RoleDefaults::FINANCER_SUPER_ADMIN,
        ]);

        Context::add('financer_id', $financer->id);

        // Can manage lower financer-level roles
        $canManage = $this->service->canManageRole($user, RoleDefaults::FINANCER_ADMIN);
        $this->assertTrue($canManage);

        $canManage = $this->service->canManageRole($user, RoleDefaults::BENEFICIARY);
        $this->assertTrue($canManage);

        // Cannot manage division-level or higher roles
        $canManage = $this->service->canManageRole($user, RoleDefaults::DIVISION_ADMIN);
        $this->assertFalse($canManage);
    }
}
