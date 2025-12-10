<?php

namespace Tests\Feature\Http\Controllers\V1\RoleController;

use App\Enums\IDP\RoleDefaults;
use App\Enums\IDP\TeamTypes;
use App\Models\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('role')]

#[Group('security')]
class BaseRoleProtectionTest extends ProtectedRouteTestCase
{
    public $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->team = ModelFactory::createTeam(['type' => TeamTypes::GLOBAL]);
        // Création des rôles de base
        foreach (RoleDefaults::getValues() as $role) {
            ModelFactory::createRole(['name' => $role, 'team_id' => $this->team->id, 'is_protected' => true]);
        }
        //        $this->withoutExceptionHandling();

    }

    #[Test]
    public function base_roles_cannot_be_modified(): void
    {
        $role = Role::where('name', RoleDefaults::HEXEKO_SUPER_ADMIN)
            ->where('team_id', $this->team->id)
            ->first();

        $this->patchJson(route('roles.update', ['role' => $role->id]), [
            'name' => 'modified_super_admin',
            'guard_name' => 'api',
        ])->assertStatus(422); // Vérifie que la modification est interdite

        $this->assertEquals(RoleDefaults::HEXEKO_SUPER_ADMIN, $role->fresh()->name);
    }

    #[Test]
    public function base_roles_cannot_be_deleted(): void
    {
        $role = Role::where('name', RoleDefaults::FINANCER_SUPER_ADMIN)
            ->where('team_id', $this->team->id)
            ->first();

        $this->deleteJson(route('roles.destroy', ['role' => $role->id]))
            ->assertStatus(422); // Vérifie que la suppression est interdite

        $this->assertNotNull(Role::find($role->id));
    }
}
