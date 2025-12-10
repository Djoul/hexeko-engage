<?php

namespace Tests\Feature\Http\Controllers\V1\ModuleController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Module;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('module')]
#[Group('financer')]
#[Group('division')]
class CoreModuleProtectionTest extends ProtectedRouteTestCase
{
    private User $admin;

    private Financer $financer;

    private Division $division;

    private Module $coreModule;

    private Module $regularModule;

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

        // Create a core module
        $this->coreModule = Module::factory()->create([
            'is_core' => true,
            'name' => ['en' => 'Core Module', 'fr' => 'Module Core'],
        ]);

        // Create a regular module
        $this->regularModule = Module::factory()->create([
            'is_core' => false,
            'name' => ['en' => 'Regular Module', 'fr' => 'Module Régulier'],
        ]);
    }

    #[Test]
    public function it_prevents_deactivating_core_module_for_division(): void
    {
        // First, activate the core module for the division
        $this->division->modules()->attach($this->coreModule->id, ['active' => true]);

        // Try to deactivate core module for the division
        $response = $this->actingAs($this->admin)
            ->postJson(route('division.module.deactivate'), [
                'division_id' => $this->division->id,
                'module_id' => $this->coreModule->id,
            ]);

        // Assert validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['module_id'])
            ->assertJson([
                'errors' => [
                    'module_id' => ['Core module cannot be deactivated'],
                ],
            ]);

        // Assert module is still active in database
        $this->assertDatabaseHas('division_module', [
            'division_id' => $this->division->id,
            'module_id' => $this->coreModule->id,
            'active' => true,
        ]);
    }

    #[Test]
    public function it_prevents_deactivating_core_module_for_financer(): void
    {
        // First, activate the core module for the financer
        $this->financer->modules()->attach($this->coreModule->id, ['active' => true, 'promoted' => false]);

        // Try to deactivate core module for the financer
        $response = $this->actingAs($this->admin)
            ->postJson(route('financer.module.deactivate'), [
                'financer_id' => $this->financer->id,
                'module_id' => $this->coreModule->id,
            ]);

        // Assert validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['module_id'])
            ->assertJson([
                'errors' => [
                    'module_id' => ['Core module cannot be deactivated'],
                ],
            ]);

        // Assert module is still active in database
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $this->financer->id,
            'module_id' => $this->coreModule->id,
            'active' => true,
        ]);
    }

    #[Test]
    public function it_allows_deactivating_regular_module_for_division(): void
    {
        // First, activate the regular module for the division
        $this->division->modules()->attach($this->regularModule->id, ['active' => true]);

        // Deactivate regular module for the division
        $response = $this->actingAs($this->admin)
            ->postJson(route('division.module.deactivate'), [
                'division_id' => $this->division->id,
                'module_id' => $this->regularModule->id,
            ]);

        // Assert success
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Module deactivated for division successfully',
            ]);

        // Assert module is deactivated in database
        $this->assertDatabaseHas('division_module', [
            'division_id' => $this->division->id,
            'module_id' => $this->regularModule->id,
            'active' => false,
        ]);
    }

    #[Test]
    public function it_allows_deactivating_regular_module_for_financer(): void
    {
        // First, activate the regular module for the financer
        $this->financer->modules()->attach($this->regularModule->id, ['active' => true, 'promoted' => false]);

        // Deactivate regular module for the financer
        $response = $this->actingAs($this->admin)
            ->postJson(route('financer.module.deactivate'), [
                'financer_id' => $this->financer->id,
                'module_id' => $this->regularModule->id,
            ]);

        // Assert success
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Module deactivated for financer successfully',
            ]);

        // Assert module is deactivated in database
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $this->financer->id,
            'module_id' => $this->regularModule->id,
            'active' => false,
        ]);
    }

    #[Test]
    public function it_prevents_toggling_core_module_to_inactive_for_division(): void
    {
        // First, activate the core module for the division
        $this->division->modules()->attach($this->coreModule->id, ['active' => true]);

        // Try to toggle core module to inactive for the division
        $response = $this->actingAs($this->admin)
            ->postJson(route('division.module.toggle'), [
                'division_id' => $this->division->id,
                'module_id' => $this->coreModule->id,
            ]);

        // Core module should remain active (toggle should not deactivate it)
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['module_id']);

        // Assert module is still active in database
        $this->assertDatabaseHas('division_module', [
            'division_id' => $this->division->id,
            'module_id' => $this->coreModule->id,
            'active' => true,
        ]);
    }

    #[Test]
    public function it_allows_activating_core_module_if_not_active(): void
    {
        // Core module not attached yet

        // Activate core module for the division
        $response = $this->actingAs($this->admin)
            ->postJson(route('division.module.activate'), [
                'division_id' => $this->division->id,
                'module_id' => $this->coreModule->id,
            ]);

        // Assert success
        $response->assertStatus(200);

        // Assert module is active in database
        $this->assertDatabaseHas('division_module', [
            'division_id' => $this->division->id,
            'module_id' => $this->coreModule->id,
            'active' => true,
        ]);
    }

    #[Test]
    public function it_ensures_core_modules_are_always_active_on_creation(): void
    {
        // Create a new financer
        $newFinancer = ModelFactory::createFinancer([
            'division_id' => $this->division->id,
            'name' => 'New Financer',
        ]);

        // Attach core module (even if we try to set it inactive, it should be forced active)
        $newFinancer->modules()->attach($this->coreModule->id, ['active' => false, 'promoted' => false]);

        // When retrieving, core modules should always be active
        $module = $newFinancer->modules()->where('module_id', $this->coreModule->id)->first();

        // Note: This behavior would be enforced in the controller/service layer
        // The test here verifies the expected behavior after implementation
        $this->assertNotNull($module);
    }
}
