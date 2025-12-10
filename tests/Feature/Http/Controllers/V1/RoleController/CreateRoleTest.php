<?php

namespace Tests\Feature\Http\Controllers\V1\RoleController;

use App\Models\Role;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['roles'], scope: 'test')]
#[Group('role')]
class CreateRoleTest extends ProtectedRouteTestCase
{
    use WithFaker;

    protected $createRoleAction;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_can_create_role(): void
    {
        $initialCount = Role::count();
        $this->assertDatabaseCount('roles', $initialCount);

        $roleData = ModelFactory::makeRole(['name' => 'Role Test'])
            ->toArray();

        $response = $this->post(
            '/api/v1/roles',
            $roleData,
            ['Accept' => 'application/json']
        );

        $response->assertStatus(201);

        $this->assertDatabaseCount('roles', $initialCount + 1);

        $this->assertDatabaseHas('roles', ['name' => $roleData['name']]);
    }
}
