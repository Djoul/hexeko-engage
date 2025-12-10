<?php

namespace Tests\Feature\Http\Controllers\V1\PermissionController;

use App\Models\Permission;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['permissions'], scope: 'test')]
#[Group('permission')]
class UpdatePermissionTest extends ProtectedRouteTestCase
{
    use WithFaker;

    const URI = '/api/v1/permissions/';

    protected function setUp(): void
    {
        parent::setUp();

    }

    #[Test]
    public function it_can_update_permission(): void
    {
        $permission = Permission::factory()
            ->create(['name' => 'Permission Test']);

        $updatedData = [
            ...$permission->toArray(),
            'name' => 'Updated Permission Test',
        ];

        $this->assertDatabaseCount('permissions', 1);
        $response = $this->put(self::URI."{$permission->id}", $updatedData, ['Accept' => 'application/json']);

        $response->assertStatus(200);

        $this->assertDatabaseCount('permissions', 1);
        $this->assertDatabaseHas('permissions', ['id' => $permission['id'], 'name' => $updatedData['name']]);

    }
}
