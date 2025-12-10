<?php

namespace Tests\Feature\Http\Controllers\V1\PermissionController;

use App\Models\Permission;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('permission')]
class FetchPermissionByIdTest extends ProtectedRouteTestCase
{
    use WithFaker;

    protected $createPermissionAction;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_can_fetch_a_single_permission(): void
    {
        $permission = Permission::factory()->create(['name' => 'Permission Test']);

        $response = $this->get('/api/v1/permissions/'.$permission->id);

        $response->assertStatus(200);
    }
}
