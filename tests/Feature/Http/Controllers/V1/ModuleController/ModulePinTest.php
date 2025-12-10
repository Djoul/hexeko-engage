<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\ModuleController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Module;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('module')]

class ModulePinTest extends ProtectedRouteTestCase
{
    private User $user;

    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with context
        $this->user = $this->createAuthUser(
            role: RoleDefaults::BENEFICIARY,
            withContext: true
        );

        $this->module = Module::factory()->create();
    }

    #[Test]
    public function user_can_pin_a_module(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/modules/pin', [
            'module_id' => $this->module->id,
        ]);

        $response->assertOk();
        $response->assertJson(['message' => 'Module épinglé avec succès']);
        $this->assertDatabaseHas('user_pinned_modules', [
            'user_id' => $this->user->id,
            'module_id' => $this->module->id,
        ]);
    }

    #[Test]
    public function user_can_unpin_a_module(): void
    {
        $this->user->pinnedModules()->attach($this->module->id);
        $response = $this->actingAs($this->user)->postJson('/api/v1/modules/unpin', [
            'module_id' => $this->module->id,
        ]);

        $response->assertOk();
        $response->assertJson(['message' => 'Module désépinglé avec succès']);
        $this->assertDatabaseMissing('user_pinned_modules', [
            'user_id' => $this->user->id,
            'module_id' => $this->module->id,
        ]);
    }
}
