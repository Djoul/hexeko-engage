<?php

namespace Tests\Feature\Http\Controllers\V1\RoleController;

use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('role')]

class FetchRoleByIdTest extends ProtectedRouteTestCase
{
    use WithFaker;

    protected $createRoleAction;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_can_fetch_a_single_role(): void
    {
        $role = ModelFactory::createRole(['name' => 'Role Test']);

        $response = $this->get('/api/v1/roles/'.$role->id);

        $response->assertStatus(200);
    }
}
