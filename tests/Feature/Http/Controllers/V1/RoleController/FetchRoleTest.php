<?php

namespace Tests\Feature\Http\Controllers\V1\RoleController;

use DB;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('role')]

class FetchRoleTest extends ProtectedRouteTestCase
{
    use WithFaker;

    const URI = '/api/v1/roles';

    protected function setUp(): void
    {
        parent::setUp();
        // Delete dependent tables first to avoid foreign key constraints
        DB::table('role_has_permissions')->delete();
        DB::table('model_has_roles')->delete();
        DB::table('roles')->delete();
    }

    #[Test]
    public function it_can_fetch_all_role(): void
    {
        $count = 10;

        $team_id = ModelFactory::createTeam();
        setPermissionsTeamId($team_id);

        for ($i = 0; $i < $count; $i++) {
            ModelFactory::createRole(['team_id' => $team_id, 'name' => 'Role Test '.$i]);
        }

        $response = $this->get(self::URI);

        $response->assertStatus(200);

        $this->assertDatabaseCount('roles', $count);

    }
}
