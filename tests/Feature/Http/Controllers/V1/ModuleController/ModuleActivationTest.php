<?php

namespace Tests\Feature\Http\Controllers\V1\ModuleController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Module;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('module')]

class ModuleActivationTest extends ProtectedRouteTestCase
{
    private User $admin;

    private Financer $financer;

    private Division $division;

    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with context
        $this->admin = $this->createAuthUser(
            role: RoleDefaults::HEXEKO_SUPER_ADMIN,
            withContext: true,
            returnDetails: true
        );

        // Get division and financer from parent class properties
        $this->division = $this->currentDivision;
        $this->financer = $this->currentFinancer;

        // Create a module
        $this->module = Module::factory()->create();
    }

    #[Test]
    public function admin_can_activate_a_module_for_a_division(): void
    {
        // API request to activate module for the division
        $response = $this->actingAs($this->admin)
            ->postJson(route('division.module.activate', [
                'division_id' => $this->division->id,
                'module_id' => $this->module->id,
            ]));

        // Assert the response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Module activated for division successfully',
            ]);

        // Assert module is correctly activated for the division
        $this->assertDatabaseHas('division_module', [
            'division_id' => $this->division->id,
            'module_id' => $this->module->id,
            'active' => true,
        ]);
    }

    #[Test]
    public function admin_can_activate_a_module_for_a_financer_if_it_is_enabled_in_at_least_one_division(): void
    {
        // First, activate the module for the division
        $this->division->modules()->attach($this->module->id, ['active' => true]);

        // API request to activate module for the financer
        $response = $this->actingAs($this->admin)
            ->postJson(route('financer.module.activate', [
                'financer_id' => $this->financer->id,
                'module_id' => $this->module->id,
            ]));

        // Assert the response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Module activated for financer successfully',
            ]);

        // Assert module is correctly activated for the financer
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $this->financer->id,
            'module_id' => $this->module->id,
            'active' => true,
        ]);
    }

    #[Test]
    public function admin_cannot_activate_a_module_for_a_financer_if_it_is_not_enabled_in_any_division(): void
    {
        // Ensure the module is NOT activated in any division
        $this->assertDatabaseMissing('division_module', [
            'module_id' => $this->module->id,
            'active' => true,
        ]);

        // API request to activate module for the financer
        $response = $this->actingAs($this->admin)
            ->postJson(route('financer.module.activate', [
                'financer_id' => $this->financer->id,
                'module_id' => $this->module->id,
            ]));

        // Assert the response
        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Module must be active in the financer\'s division before activating it for a financer',
            ]);

        // Ensure module was NOT activated for the financer
        $this->assertDatabaseMissing('financer_module', [
            'financer_id' => $this->financer->id,
            'module_id' => $this->module->id,
            'active' => true,
        ]);
    }
}
