<?php

namespace Tests\Feature\Http\Controllers\V1\PermissionController;

use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['permissions'], scope: 'test')]
#[Group('permission')]
class CreatePermissionTest extends ProtectedRouteTestCase
{
    use WithFaker;

    protected $createPermissionAction;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_can_create_permission(): void
    {
        $this->assertDatabaseCount('permissions', 0);

        $permissionData = ModelFactory::makePermission(['name' => 'Permission Test'])->toArray();

        $response = $this->post('/api/v1/permissions', $permissionData);

        $response->assertStatus(201);

        $this->assertDatabaseCount('permissions', 1);

        $this->assertDatabaseHas('permissions', ['name' => $permissionData['name']]);
    }
}
