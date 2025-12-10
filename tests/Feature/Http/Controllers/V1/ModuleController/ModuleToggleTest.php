<?php

namespace Tests\Feature\Http\Controllers\V1\ModuleController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Division;
use App\Models\Module;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('module')]

class ModuleToggleTest extends ProtectedRouteTestCase
{
    private User $admin;

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

        // Get division from parent class properties
        $this->division = $this->currentDivision;

        // Create a module
        $this->module = Module::factory()->create();
    }

    #[Test]
    public function admin_can_toggle_module_activation_for_division_from_inactive_to_active(): void
    {
        // Ensure the module is not active for the division
        $this->assertDatabaseMissing('division_module', [
            'division_id' => $this->division->id,
            'module_id' => $this->module->id,
        ]);

        // API request to toggle module for the division
        $response = $this->actingAs($this->admin)
            ->postJson(route('division.module.toggle', [
                'division_id' => $this->division->id,
                'module_id' => $this->module->id,
            ]));

        // Assert the response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Module activated for division successfully',
                'active' => true,
            ]);

        // Assert module is correctly activated for the division
        $this->assertDatabaseHas('division_module', [
            'division_id' => $this->division->id,
            'module_id' => $this->module->id,
            'active' => true,
        ]);
    }

    #[Test]
    public function admin_can_toggle_module_activation_for_division_from_active_to_inactive(): void
    {
        // First, activate the module for the division
        $this->division->modules()->attach($this->module->id, ['active' => true]);

        // Verify the module is active
        $this->assertDatabaseHas('division_module', [
            'division_id' => $this->division->id,
            'module_id' => $this->module->id,
            'active' => true,
        ]);

        // API request to toggle module for the division
        $response = $this->actingAs($this->admin)
            ->postJson(route('division.module.toggle', [
                'division_id' => $this->division->id,
                'module_id' => $this->module->id,
            ]));

        // Assert the response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Module deactivated for division successfully',
                'active' => false,
            ]);

        // Assert module is correctly deactivated for the division
        $this->assertDatabaseHas('division_module', [
            'division_id' => $this->division->id,
            'module_id' => $this->module->id,
            'active' => false,
        ]);
    }

    #[Test]
    public function admin_can_bulk_toggle_multiple_modules_for_division(): void
    {
        // Create additional modules
        $module2 = Module::factory()->create();
        $module3 = Module::factory()->create();

        // Activate one of the modules
        $this->division->modules()->attach($module2->id, ['active' => true]);

        // Verify module2 is active
        $this->assertDatabaseHas('division_module', [
            'division_id' => $this->division->id,
            'module_id' => $module2->id,
            'active' => true,
        ]);

        // API request to bulk toggle modules for the division
        $response = $this->actingAs($this->admin)
            ->postJson(route('division.module.bulk-toggle', [
                'division_id' => $this->division->id,
                'module_ids' => [
                    $this->module->id,
                    $module2->id,
                    $module3->id,
                ],
            ]));

        // Assert the response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Modules toggled for division successfully',
            ]);

        // Get the response data
        $responseData = $response->json();

        // Assert the toggle results match what we expect
        $this->assertTrue($responseData['results'][$this->module->id]);
        $this->assertFalse($responseData['results'][$module2->id]);
        $this->assertTrue($responseData['results'][$module3->id]);

        // Assert modules are correctly toggled in the database
        $this->assertDatabaseHas('division_module', [
            'division_id' => $this->division->id,
            'module_id' => $this->module->id,
            'active' => true,
        ]);

        $this->assertDatabaseHas('division_module', [
            'division_id' => $this->division->id,
            'module_id' => $module2->id,
            'active' => false,
        ]);

        $this->assertDatabaseHas('division_module', [
            'division_id' => $this->division->id,
            'module_id' => $module3->id,
            'active' => true,
        ]);
    }
}
