<?php

namespace Tests\Feature\Http\Controllers\V1\RoleController;

use DB;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('role')]

class UpdateRoleTest extends ProtectedRouteTestCase
{
    use WithFaker;

    const URI = '/api/v1/roles/';

    #[Test]
    public function it_can_update_role(): void
    {

        // Delete dependent tables first to avoid foreign key constraints
        DB::table('role_has_permissions')->delete();
        DB::table('model_has_roles')->delete();
        DB::table('roles')->delete();

        $teamId = ModelFactory::createTeam()->id;

        setPermissionsTeamId($teamId);

        $role = ModelFactory::createRole(['name' => 'Role Test']);

        $updatedData = [
            ...$role->toArray(),
            'name' => 'Role Test Updated',
        ];

        $this->assertDatabaseCount('roles', 1);
        $response = $this->put(self::URI."{$role->id}", $updatedData);

        $response->assertStatus(200);

        $this->assertDatabaseCount('roles', 1);
        $this->assertDatabaseHas('roles', ['id' => $role['id'], 'name' => $updatedData['name']]);

    }
}
