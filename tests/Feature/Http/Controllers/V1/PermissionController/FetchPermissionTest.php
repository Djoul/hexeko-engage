<?php

namespace Tests\Feature\Http\Controllers\V1\PermissionController;

use App\Models\Permission;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['permissions'])]
#[Group('permission')]
class FetchPermissionTest extends ProtectedRouteTestCase
{
    use WithFaker;

    const URI = '/api/v1/permissions';

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_can_fetch_all_permission(): void
    {

        Permission::factory()->count(10)->create();

        $response = $this->get(self::URI);

        $response->assertStatus(200);

        $this->assertDatabaseCount('permissions', 10);

    }
}
