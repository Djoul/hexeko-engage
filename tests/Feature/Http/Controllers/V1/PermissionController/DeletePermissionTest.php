<?php

namespace Tests\Feature\Http\Controllers\V1\PermissionController;

use App\Models\Permission;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('permission')]
class DeletePermissionTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_can_delete_permission(): void
    {
        $permission = Permission::factory()->create();

        $this->assertDatabasehas('permissions', ['id' => $permission['id'], 'deleted_at' => null]);

        $response = $this->delete("/api/v1/permissions/{$permission->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('permissions', ['id' => $permission['id'], 'deleted_at' => null]);
    }
}
