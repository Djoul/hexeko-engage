<?php

namespace Tests\Feature\Http\Controllers\V1\ModuleController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Module;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('module')]

class DeleteModuleTest extends ProtectedRouteTestCase
{
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
    public function it_can_delete_module(): void
    {
        $module = Module::factory()->create();

        $this->assertDatabasehas('modules', ['id' => $module['id'], 'deleted_at' => null]);

        $response = $this->actingAs($this->auth)->delete("/api/v1/modules/{$module->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('modules', ['id' => $module['id'], 'deleted_at' => null]);
    }
}
