<?php

namespace Tests\Feature\Http\Controllers\V1\ModuleController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Module;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('module')]

class FetchModuleByIdTest extends ProtectedRouteTestCase
{
    use WithFaker;

    protected $createModuleAction;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with context
        $this->auth = $this->createAuthUser(
            role: RoleDefaults::HEXEKO_SUPER_ADMIN,
            withContext: true
        );
    }

    #[Test]
    public function it_can_fetch_a_single_module(): void
    {
        $module = Module::factory()->create(['name' => 'Module Test']);

        $response = $this->actingAs($this->auth)->get('/api/v1/modules/'.$module->id);

        $response->assertStatus(200);
    }
}
