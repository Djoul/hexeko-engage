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

class ModuleDeactivationTest extends ProtectedRouteTestCase
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
    public function admin_can_deactivate_a_module_for_a_division(): void
    {
        // First, activate the module for the division
        $this->division->modules()->attach($this->module->id, ['active' => true]);

        // API request to deactivate module for the division
        $response = $this->actingAs($this->admin)
            ->postJson(route('division.module.deactivate', [
                'division_id' => $this->division->id,
                'module_id' => $this->module->id,
            ]));

        // Assert the response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Module deactivated for division successfully',
            ]);

        // Assert module is correctly deactivated for the division
        $this->assertDatabaseHas('division_module', [
            'division_id' => $this->division->id,
            'module_id' => $this->module->id,
            'active' => false,
        ]);
    }

    #[Test]
    public function admin_can_deactivate_a_module_for_a_financer(): void
    {
        // First, activate the module for the division and financer
        $this->division->modules()->attach($this->module->id, ['active' => true]);
        $this->financer->modules()->attach($this->module->id, ['active' => true]);

        // API request to deactivate module for the financer
        $response = $this->actingAs($this->admin)
            ->postJson(route('financer.module.deactivate', [
                'financer_id' => $this->financer->id,
                'module_id' => $this->module->id,
            ]));

        // Assert the response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Module deactivated for financer successfully',
            ]);

        // Assert module is correctly deactivated for the financer
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $this->financer->id,
            'module_id' => $this->module->id,
            'active' => false,
        ]);
    }

    #[Test]
    public function deactivating_module_for_division_also_deactivates_it_for_related_financers(): void
    {
        // First, activate the module for the division and financer
        $this->division->modules()->attach($this->module->id, ['active' => true]);
        $this->financer->modules()->attach($this->module->id, ['active' => true]);

        // API request to deactivate module for the division
        $response = $this->actingAs($this->admin)
            ->postJson(route('division.module.deactivate', [
                'division_id' => $this->division->id,
                'module_id' => $this->module->id,
            ]));

        // Assert the response
        $response->assertStatus(200);

        // Assert module is correctly deactivated for the division
        $this->assertDatabaseHas('division_module', [
            'division_id' => $this->division->id,
            'module_id' => $this->module->id,
            'active' => false,
        ]);

        // Assert module is also deactivated for the financer
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $this->financer->id,
            'module_id' => $this->module->id,
            'active' => false,
        ]);
    }
}
