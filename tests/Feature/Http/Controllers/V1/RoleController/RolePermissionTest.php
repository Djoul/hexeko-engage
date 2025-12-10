<?php

namespace Tests\Feature\Http\Controllers\V1\RoleController;

use App\Enums\IDP\PermissionDefaults;
use App\Enums\IDP\RoleDefaults;
use App\Enums\IDP\TeamTypes;
use App\Models\Role;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('role')]
#[Group('permission')]

class RolePermissionTest extends ProtectedRouteTestCase
{
    public $team;

    #[Test]
    public function a_user_can_add_permission_to_a_role(): void
    {

        $role = ModelFactory::createRole(['name' => RoleDefaults::FINANCER_SUPER_ADMIN, 'team_id' => $this->team->id]);
        ModelFactory::createRole(['name' => RoleDefaults::DIVISION_ADMIN, 'team_id' => $this->team->id]);

        $permission = ModelFactory::createPermission(['name' => PermissionDefaults::CREATE_USER]);

        $auth = $this->createAuth(
            roleName: RoleDefaults::DIVISION_ADMIN,
            permissionName: PermissionDefaults::ASSIGN_ROLES
        );

        $this->actingAs($auth)->postJson(
            route('roles.add_permission', ['role' => $role->id, 'permission' => $permission->id])
        )
            ->assertStatus(200);

        $this->assertTrue($role->hasPermissionTo($permission->name));
    }

    /**
     * @return User|User[]
     */
    protected function createAuth($roleName = null, $permissionName = null): User
    {
        if (! $roleName) {
            $roleName = RoleDefaults::HEXEKO_SUPER_ADMIN;
        }

        $role = Role::findByName($roleName);

        $permission = ModelFactory::createPermission(['name' => $permissionName]);

        $role->givePermissionTo($permission);

        return $this->createAuthUser($roleName, $this->team);
    }

    #[Test]
    public function a_user_cannot_add_permission_to_a_role_higher_then_self(): void
    {
        $role = ModelFactory::createRole(['name' => RoleDefaults::HEXEKO_SUPER_ADMIN, 'team_id' => $this->team->id]);
        ModelFactory::createRole(['name' => RoleDefaults::DIVISION_ADMIN, 'team_id' => $this->team->id]);

        $permission = ModelFactory::createPermission(['name' => PermissionDefaults::CREATE_USER]);

        $auth = $this->createAuth(
            roleName: RoleDefaults::DIVISION_ADMIN,
            permissionName: PermissionDefaults::ASSIGN_ROLES
        );

        $this->actingAs($auth)->postJson(
            route('roles.add_permission', ['role' => $role->id, 'permission' => $permission->id])
        )
            ->assertStatus(403);

        $this->assertFalse($role->hasPermissionTo($permission->name));
    }

    #[Test]
    public function a_user_cannot_add_permission_to_his_own_role(): void
    {
        $role = ModelFactory::createRole(['name' => RoleDefaults::DIVISION_ADMIN, 'team_id' => $this->team->id]);

        $permission = ModelFactory::createPermission(['name' => PermissionDefaults::CREATE_USER]);

        $auth = $this->createAuth(
            roleName: RoleDefaults::DIVISION_ADMIN,
            permissionName: PermissionDefaults::ASSIGN_ROLES
        );

        $this->actingAs($auth)->postJson(
            route('roles.add_permission', ['role' => $role->id, 'permission' => $permission->id])
        )
            ->assertStatus(403);

        $this->assertFalse($role->hasPermissionTo($permission->name));
    }

    #[Test]
    public function a_user_can_remove_permission_from_a_role(): void
    {
        $role = ModelFactory::createRole(['name' => RoleDefaults::FINANCER_SUPER_ADMIN, 'team_id' => $this->team->id]);
        $permission = ModelFactory::createPermission(['name' => PermissionDefaults::CREATE_USER]);

        ModelFactory::createRole(['name' => RoleDefaults::DIVISION_ADMIN, 'team_id' => $this->team->id]);

        $role->givePermissionTo($permission->name);

        $auth = $this->createAuth(
            roleName: RoleDefaults::DIVISION_ADMIN,
            permissionName: PermissionDefaults::REMOVE_PERMISSION_FROM_ROLE
        );

        $this->actingAs($auth)->deleteJson(
            route('roles.remove_permission', ['role' => $role->id, 'permission' => $permission->id])
        )
            ->assertStatus(200);

        $this->assertFalse($role->hasPermissionTo($permission->name));
    }

    #[Test]
    public function a_user_cannot_remove_permission_from_a_higher_role(): void
    {
        $permission = ModelFactory::createPermission();

        $role = ModelFactory::createRole(['name' => RoleDefaults::HEXEKO_SUPER_ADMIN, 'team_id' => $this->team->id]);
        ModelFactory::createRole(['name' => RoleDefaults::DIVISION_ADMIN, 'team_id' => $this->team->id]);

        $role->givePermissionTo($permission->name);

        $auth = $this->createAuth(
            roleName: RoleDefaults::DIVISION_ADMIN,
            permissionName: PermissionDefaults::ASSIGN_ROLES
        );

        $this->actingAs($auth)->deleteJson(
            route('roles.remove_permission', ['role' => $role->id, 'permission' => $permission->id])
        )
            ->assertStatus(403);

        $this->assertTrue($role->hasPermissionTo($permission->name));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->team = ModelFactory::createTeam(['type' => TeamTypes::GLOBAL]);
    }
}
