<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\ModuleController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Module;
use DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('module')]
#[Group('pricing-history')]
class ModuleActivationAndPricingTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private Division $division;

    private Financer $financer;

    private Module $module;

    private Module $coreModule;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with division and financer
        $this->auth = $this->createAuthUser(
            role: RoleDefaults::DIVISION_SUPER_ADMIN,
            withContext: true,
            returnDetails: true
        );

        // Assign from parent class properties for backward compatibility
        $this->division = $this->currentDivision;
        $this->financer = $this->currentFinancer;

        // Create test modules
        $this->coreModule = Module::factory()->create([
            'name' => 'Core Module',
            'is_core' => true,
        ]);
        $this->module = Module::factory()->create([
            'name' => 'Regular Module',
            'is_core' => false,
        ]);
    }

    #[Test]
    public function it_lists_division_modules_with_pricing(): void
    {
        // Arrange
        $this->division->modules()->attach($this->module->id, [
            'active' => true,
            'price_per_beneficiary' => 1500,
        ]);
        $this->division->modules()->attach($this->coreModule->id, [
            'active' => true,
            'price_per_beneficiary' => null,
        ]);
        $this->division->core_package_price = 5000;
        $this->division->save();

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson("/api/v1/divisions/{$this->division->id}/modules");

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'is_core',
                        'active',
                        'price_per_beneficiary',
                    ],
                ],
            ]);

        // Check core module is included and active
        $data = $response->json('data');
        $coreModule = collect($data)->firstWhere('id', $this->coreModule->id);
        $this->assertNotNull($coreModule);
        $this->assertTrue($coreModule['is_core']);
        $this->assertTrue($coreModule['active']);
        $this->assertNull($coreModule['price_per_beneficiary']);

        // Check regular module has price
        $regularModule = collect($data)->firstWhere('id', $this->module->id);
        $this->assertNotNull($regularModule);
        $this->assertFalse($regularModule['is_core']);
        $this->assertTrue($regularModule['active']);
        $this->assertEquals(1500, $regularModule['price_per_beneficiary']);
    }

    #[Test]
    public function it_lists_financer_modules_with_pricing(): void
    {
        // Arrange
        $this->financer->modules()->attach($this->module->id, [
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => 2000,
        ]);
        $this->financer->modules()->attach($this->coreModule->id, [
            'active' => true,
            'promoted' => true,
            'price_per_beneficiary' => null,
        ]);
        $this->financer->core_package_price = 6000;
        $this->financer->save();

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson("/api/v1/financers/{$this->financer->id}/modules");

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'is_core',
                        'active',
                        'promoted',
                        'price_per_beneficiary',
                    ],
                ],
            ]);

        // Check promoted status
        $data = $response->json('data');
        $coreModule = collect($data)->firstWhere('is_core', true);
        $this->assertTrue($coreModule['promoted']);
    }

    #[Test]
    public function it_updates_division_modules_with_pricing(): void
    {
        // Act
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/divisions/{$this->division->id}/modules", [
                'core_package_price' => 100,
                'modules' => [
                    [
                        'id' => $this->module->id,
                        'active' => true,
                        'price_per_beneficiary' => 1800,
                    ],
                    [
                        'id' => $this->coreModule->id,
                        'active' => true,
                        'price_per_beneficiary' => null,
                    ],
                ],
            ]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('message', 'Division modules updated successfully');

        $moduleRelation = $this->division->modules()
            ->where('module_id', $this->module->id)
            ->first();
        $this->assertNotNull($moduleRelation);
        $this->assertEquals(1800, $moduleRelation->pivot->price_per_beneficiary);
        $this->division->refresh();
        $this->assertEquals(100, $this->division->core_package_price);

    }

    #[Test]
    public function it_updates_financer_modules_with_pricing(): void
    {
        // Arrange - activate modules for division first (business rule requirement)
        $this->division->modules()->attach($this->module->id, [
            'active' => true,
        ]);
        $this->division->modules()->attach($this->coreModule->id, [
            'active' => true,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/financers/{$this->financer->id}/modules", [
                'core_package_price' => 100,
                'modules' => [
                    [
                        'id' => $this->module->id,
                        'active' => true,
                        'promoted' => true,
                        'price_per_beneficiary' => 2200,
                    ],
                    [
                        'id' => $this->coreModule->id,
                        'active' => true,
                        'promoted' => false,
                        'price_per_beneficiary' => null,
                    ],
                ],
            ]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('message', 'Financer modules updated successfully');

        $moduleRelation = $this->financer->modules()
            ->where('module_id', $this->module->id)
            ->first();
        $this->assertNotNull($moduleRelation);
        $this->assertEquals(2200, $moduleRelation->pivot->price_per_beneficiary);
        $this->assertTrue($moduleRelation->pivot->promoted);

        $this->financer->refresh();
        $this->assertEquals(100, $this->financer->core_package_price);
    }

    #[Test]
    public function it_prevents_deactivating_core_module_for_division(): void
    {
        // Arrange
        $this->division->modules()->attach($this->coreModule->id, [
            'active' => true,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/divisions/{$this->division->id}/modules", [
                'modules' => [
                    [
                        'id' => $this->coreModule->id,
                        'active' => false, // Trying to deactivate core module
                    ],
                ],
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['modules.0.active']);
    }

    #[Test]
    public function it_prevents_deactivating_core_module_for_financer(): void
    {
        // Arrange
        $this->financer->modules()->attach($this->coreModule->id, [
            'active' => true,
            'promoted' => false,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/financers/{$this->financer->id}/modules", [
                'modules' => [
                    [
                        'id' => $this->coreModule->id,
                        'active' => false, // Trying to deactivate core module
                        'promoted' => false,
                    ],
                ],
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['modules.0.active']);
    }

    #[Test]
    public function it_validates_pricing_is_non_negative(): void
    {
        // Act
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/divisions/{$this->division->id}/modules", [
                'modules' => [
                    [
                        'id' => $this->module->id,
                        'active' => true,
                        'price_per_beneficiary' => -50, // Negative price
                    ],
                ],
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'modules.0.price_per_beneficiary',
            ]);
    }

    #[Test]
    public function it_prevents_setting_price_on_core_module_for_division(): void
    {
        // Act - try to set a price on a core module
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/divisions/{$this->division->id}/modules", [
                'modules' => [
                    [
                        'id' => $this->coreModule->id,
                        'active' => true,
                        'price_per_beneficiary' => 1000, // Trying to set price on core module
                    ],
                ],
            ]);

        // Assert - should fail with validation error
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['modules.0.price_per_beneficiary']);
    }

    #[Test]
    public function it_prevents_setting_price_on_core_module_for_financer(): void
    {
        // Arrange - activate modules for division first (business rule requirement)
        $this->division->modules()->attach($this->coreModule->id, [
            'active' => true,
        ]);

        // Act - try to set a price on a core module
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/financers/{$this->financer->id}/modules", [
                'modules' => [
                    [
                        'id' => $this->coreModule->id,
                        'active' => true,
                        'promoted' => false,
                        'price_per_beneficiary' => 2000, // Trying to set price on core module
                    ],
                ],
            ]);

        // Assert - should fail with validation error
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['modules.0.price_per_beneficiary']);
    }

    #[Test]
    public function it_activates_module_for_division(): void
    {
        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/modules/division/activate', [
                'module_id' => $this->module->id,
                'division_id' => $this->division->id,
            ]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('message', 'Module activated for division successfully');

        $moduleRelation = $this->division->modules()
            ->where('module_id', $this->module->id)
            ->first();
        $this->assertNotNull($moduleRelation);
        $this->assertTrue($moduleRelation->pivot->active);
    }

    #[Test]
    public function it_deactivates_module_for_division(): void
    {
        // Arrange
        $this->division->modules()->attach($this->module->id, [
            'active' => true,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/modules/division/deactivate', [
                'module_id' => $this->module->id,
                'division_id' => $this->division->id,
            ]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('message', 'Module deactivated for division successfully');

        $moduleRelation = $this->division->modules()
            ->where('module_id', $this->module->id)
            ->first();
        $this->assertNotNull($moduleRelation);
        $this->assertFalse($moduleRelation->pivot->active);
    }

    #[Test]
    public function it_prevents_deactivating_core_module(): void
    {
        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/modules/division/deactivate', [
                'module_id' => $this->coreModule->id,
                'division_id' => $this->division->id,
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['module_id']);
    }

    #[Test]
    public function it_requires_module_activated_in_financers_own_division_before_financer_activation(): void
    {
        // Arrange - create a new financer in a division without the module activated
        $divisionWithoutModule = Division::factory()->create();
        $financerInDivisionWithoutModule = Financer::factory()->create(['division_id' => $divisionWithoutModule->id]);

        // Attach user to the financer
        $this->auth->financers()->attach($financerInDivisionWithoutModule->id, [
            'active' => true,
            'role' => RoleDefaults::DIVISION_SUPER_ADMIN,
            'sirh_id' => 'TEST456',
        ]);

        // Activate module in a DIFFERENT division (not the financer's division)
        $otherDivision = Division::factory()->create();
        $otherDivision->modules()->attach($this->module->id, [
            'active' => true,
        ]);

        // Act - try to activate module for financer when it's not active in the financer's own division
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/financers/{$financerInDivisionWithoutModule->id}/modules", [
                'core_package_price' => 1000,
                'modules' => [
                    [
                        'id' => $this->module->id,
                        'active' => true,
                        'promoted' => false,
                        'price_per_beneficiary' => 3000,
                    ],
                ],
            ]);

        // Assert - should fail because module is not active in the financer's specific division
        // Note: The error message is generic but the validation actually checks the financer's specific division
        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Module must be active in the financer\'s division before activating it for a financer');
    }

    #[Test]
    public function it_updates_division_core_package_price(): void
    {
        // Act
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/divisions/{$this->division->id}/core-price", [
                'core_package_price' => 7500,
            ]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('message', 'Division core package price updated successfully')
            ->assertJsonPath('core_package_price', 7500);

        // Verify in database
        $this->division->refresh();
        $this->assertEquals(7500, $this->division->core_package_price);
    }

    #[Test]
    public function it_updates_financer_core_package_price(): void
    {
        // Act
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/financers/{$this->financer->id}/core-price", [
                'core_package_price' => 8500,
            ]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('message', 'Financer core package price updated successfully')
            ->assertJsonPath('core_package_price', 8500);

        // Verify in database
        $this->financer->refresh();
        $this->assertEquals(8500, $this->financer->core_package_price);
    }

    #[Test]
    public function it_validates_core_package_price_is_non_negative_for_division(): void
    {
        // Act
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/divisions/{$this->division->id}/core-price", [
                'core_package_price' => -100,
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['core_package_price']);
    }

    #[Test]
    public function it_validates_core_package_price_is_non_negative_for_financer(): void
    {
        // Act
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/financers/{$this->financer->id}/core-price", [
                'core_package_price' => -100,
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['core_package_price']);
    }

    #[Test]
    public function it_includes_core_package_price_in_division_response(): void
    {
        // Arrange
        $this->division->core_package_price = 12000;
        $this->division->save();

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson("/api/v1/divisions/{$this->division->id}");

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.core_package_price', 12000);
    }

    #[Test]
    public function it_includes_core_package_price_in_financer_response(): void
    {
        // Arrange
        $this->financer->core_package_price = 15000;
        $this->financer->save();

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson("/api/v1/financers/{$this->financer->id}");

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.core_package_price', 15000);
    }

    #[Test]
    public function it_prevents_deactivating_core_modules_via_bulk_toggle(): void
    {
        // Arrange - Create and attach both core and regular modules
        $this->division->modules()->attach($this->coreModule->id, [
            'active' => true,
        ]);
        $this->division->modules()->attach($this->module->id, [
            'active' => true,
        ]);

        // Act - Try to toggle both modules (including core)
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/modules/division/bulk-toggle', [
                'module_ids' => [$this->coreModule->id, $this->module->id],
                'division_id' => $this->division->id,
            ]);

        // Assert - Should return an error
        $response->assertUnprocessable()
            ->assertJsonFragment([
                'message' => "Cannot toggle core modules: {$this->coreModule->name}. Core modules must always remain active.",
            ]);

        // Check that neither module was toggled
        $coreModuleRelation = $this->division->modules()
            ->where('module_id', $this->coreModule->id)
            ->first();
        $this->assertNotNull($coreModuleRelation);
        $this->assertTrue($coreModuleRelation->pivot->active, 'Core module should remain active');

        $regularModuleRelation = $this->division->modules()
            ->where('module_id', $this->module->id)
            ->first();
        $this->assertNotNull($regularModuleRelation);
        $this->assertTrue($regularModuleRelation->pivot->active, 'Regular module should also remain active since operation failed');
    }

    #[Test]
    public function it_allows_bulk_toggle_for_non_core_modules(): void
    {
        // Arrange - Create another regular module
        $anotherRegularModule = Module::factory()->create([
            'name' => 'Another Regular Module',
            'is_core' => false,
        ]);

        $this->division->modules()->attach($this->module->id, [
            'active' => true,
        ]);
        $this->division->modules()->attach($anotherRegularModule->id, [
            'active' => true,
        ]);

        // Act - Toggle only regular modules
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/modules/division/bulk-toggle', [
                'module_ids' => [$this->module->id, $anotherRegularModule->id],
                'division_id' => $this->division->id,
            ]);

        // Assert
        $response->assertOk();

        // Both regular modules should have been toggled off
        $results = $response->json('results');
        $this->assertFalse($results[$this->module->id], 'First regular module should be toggled off');
        $this->assertFalse($results[$anotherRegularModule->id], 'Second regular module should be toggled off');
    }

    #[Test]
    public function it_prevents_deactivating_core_modules_via_bulk_toggle_for_financer(): void
    {
        // Arrange - Create isolated test context
        $this->auth = $this->createAuthUser(
            role: RoleDefaults::DIVISION_SUPER_ADMIN,
            withContext: true,
            returnDetails: true
        );
        $division = $this->currentDivision;
        $financer = $this->currentFinancer;

        // Create core and regular modules
        $coreModule = Module::factory()->create([
            'name' => 'Core Module Test',
            'is_core' => true,
        ]);
        $regularModule = Module::factory()->create([
            'name' => 'Regular Module Test',
            'is_core' => false,
        ]);

        // Activate both modules for the division first
        $division->modules()->attach($coreModule->id, ['active' => true]);
        $division->modules()->attach($regularModule->id, ['active' => true]);

        // Activate both modules for the financer
        $financer->modules()->attach($coreModule->id, ['active' => true]);
        $financer->modules()->attach($regularModule->id, ['active' => true]);

        // Act - Try to bulk toggle with a core module included
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/modules/financer/bulk-toggle', [
                'module_ids' => [
                    $coreModule->id,
                    $regularModule->id,
                ],
                'financer_id' => $financer->id,
            ]);

        // Assert - Should return an error
        $response->assertUnprocessable()
            ->assertJsonFragment([
                'message' => "Cannot toggle core modules: {$coreModule->name}. Core modules must always remain active.",
            ]);

        // Verify that neither module was toggled
        $coreModuleRelation = $financer->modules()
            ->where('module_id', $coreModule->id)
            ->first();
        $regularModuleRelation = $financer->modules()
            ->where('module_id', $regularModule->id)
            ->first();

        // Check that neither module was toggled
        $this->assertTrue($coreModuleRelation->pivot->active, 'Core module should remain active');
        $this->assertTrue($regularModuleRelation->pivot->active, 'Regular module should also remain active since operation failed');
    }

    #[Test]
    public function it_allows_bulk_toggle_for_non_core_modules_for_financer(): void
    {
        // Arrange - Create isolated test context
        $this->auth = $this->createAuthUser(
            role: RoleDefaults::DIVISION_SUPER_ADMIN,
            withContext: true,
            returnDetails: true
        );
        $division = $this->currentDivision;
        $financer = $this->currentFinancer;

        // Create two regular modules
        $module1 = Module::factory()->create([
            'name' => 'Module 1',
            'is_core' => false,
        ]);
        $module2 = Module::factory()->create([
            'name' => 'Module 2',
            'is_core' => false,
        ]);

        // Activate modules for the division first
        $division->modules()->attach($module1->id, ['active' => true]);
        $division->modules()->attach($module2->id, ['active' => true]);

        // Activate module1 for the financer (module2 is not active)
        $financer->modules()->attach($module1->id, ['active' => true]);

        // Act - Bulk toggle both modules
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/modules/financer/bulk-toggle', [
                'module_ids' => [
                    $module1->id,
                    $module2->id,
                ],
                'financer_id' => $financer->id,
            ]);

        // Assert - Should be successful
        $response->assertOk()
            ->assertJson([
                'message' => 'Modules toggled for financer successfully',
                'results' => [
                    $module1->id => false, // Was active, now deactivated
                    $module2->id => true,  // Was inactive, now activated
                ],
            ]);

        // Verify modules were toggled
        $module1Relation = $financer->modules()
            ->where('module_id', $module1->id)
            ->first();
        $module2Relation = $financer->modules()
            ->where('module_id', $module2->id)
            ->first();

        $this->assertFalse($module1Relation->pivot->active, 'Module 1 should be deactivated');
        $this->assertTrue($module2Relation->pivot->active, 'Module 2 should be activated');
    }

    #[Test]
    public function it_records_pricing_history_when_division_core_price_changes(): void
    {
        // Arrange
        $this->division->core_package_price = 5000;
        $this->division->save();

        // Ensure we have a core module for history tracking
        $this->coreModule->save();

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/divisions/{$this->division->id}/core-price", [
                'core_package_price' => 7500,
            ]);

        // Assert
        $response->assertOk();

        $this->assertDatabaseHas('module_pricing_history', [
            'entity_id' => $this->division->id,
            'entity_type' => 'division',
            'price_type' => 'core_package',
            'old_price' => 5000,
            'new_price' => 7500,
            'changed_by' => $this->auth->id,
        ]);
    }

    #[Test]
    public function it_records_pricing_history_when_financer_core_price_changes(): void
    {
        // Arrange
        $this->financer->core_package_price = 6000;
        $this->financer->save();

        // Ensure we have a core module for history tracking
        $this->coreModule->save();

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/financers/{$this->financer->id}/core-price", [
                'core_package_price' => 8500,
            ]);

        // Assert
        $response->assertOk();

        $this->assertDatabaseHas('module_pricing_history', [
            'entity_id' => $this->financer->id,
            'entity_type' => 'financer',
            'price_type' => 'core_package',
            'old_price' => 6000,
            'new_price' => 8500,
            'changed_by' => $this->auth->id,
        ]);
    }

    #[Test]
    public function it_does_not_record_history_when_price_unchanged(): void
    {
        // Arrange
        $this->division->core_package_price = 5000;
        $this->division->save();

        $initialHistoryCount = DB::table('module_pricing_history')->count();

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/divisions/{$this->division->id}/core-price", [
                'core_package_price' => 5000, // Same price
            ]);

        // Assert
        $response->assertOk();

        // No new history record should be created
        $this->assertEquals($initialHistoryCount, DB::table('module_pricing_history')->count());
    }

    #[Test]
    public function it_records_module_price_history_when_division_module_price_changes(): void
    {
        // Arrange - Attach module with initial price
        $this->division->modules()->attach($this->module->id, [
            'active' => true,
            'price_per_beneficiary' => 1000,
        ]);

        // Act - Update module price
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/divisions/{$this->division->id}/modules", [
                'core_package_price' => '100',
                'modules' => [
                    [
                        'id' => $this->module->id,
                        'active' => true,
                        'price_per_beneficiary' => 1500,
                    ],
                ],
            ]);

        // Assert
        $response->assertOk();

        $this->assertDatabaseHas('module_pricing_history', [
            'module_id' => $this->module->id,
            'entity_id' => $this->division->id,
            'entity_type' => 'division',
            'price_type' => 'module_price',
            'old_price' => 1000,
            'new_price' => 1500,
            'changed_by' => $this->auth->id,
        ]);
        $this->assertDatabaseHas('module_pricing_history', [
            'entity_id' => $this->division->id,
            'entity_type' => 'division',
            'price_type' => 'core_package',
            'old_price' => null,
            'new_price' => 100,
            'changed_by' => $this->auth->id,
        ]);
    }

    #[Test]
    public function it_records_module_price_history_when_financer_module_price_changes(): void
    {
        // Arrange - Activate module for division first
        $this->division->modules()->attach($this->module->id, [
            'active' => true,
            'price_per_beneficiary' => 1000,
        ]);

        // Attach module to financer with initial price
        $this->financer->modules()->attach($this->module->id, [
            'active' => true,
            'price_per_beneficiary' => 2000,
        ]);

        // Act - Update module price for financer
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/financers/{$this->financer->id}/modules", [
                'core_package_price' => '100',
                'modules' => [
                    [
                        'id' => $this->module->id,
                        'active' => true,
                        'promoted' => false,
                        'price_per_beneficiary' => 2500,
                    ],
                ],
            ]);

        // Assert
        $response->assertOk();

        $this->assertDatabaseHas('module_pricing_history', [
            'module_id' => $this->module->id,
            'entity_id' => $this->financer->id,
            'entity_type' => 'financer',
            'price_type' => 'module_price',
            'old_price' => 2000,
            'new_price' => 2500,
            'changed_by' => $this->auth->id,
        ]);
        $this->assertDatabaseHas('module_pricing_history', [
            'entity_id' => $this->financer->id,
            'entity_type' => 'financer',
            'price_type' => 'core_package',
            'old_price' => null,
            'new_price' => 100,
            'changed_by' => $this->auth->id,
        ]);

    }

    #[Test]
    public function it_stores_prices_in_cents(): void
    {
        // Arrange
        $this->division->core_package_price = null;
        $this->division->save();

        // Act - Set price to 10€ (1000 cents)
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/divisions/{$this->division->id}/core-price", [
                'core_package_price' => 1000, // 10€ in cents
            ]);

        // Assert
        $response->assertOk();

        $this->assertDatabaseHas('divisions', [
            'id' => $this->division->id,
            'core_package_price' => 1000,
        ]);

        $this->assertDatabaseHas('module_pricing_history', [
            'entity_id' => $this->division->id,
            'entity_type' => 'division',
            'price_type' => 'core_package',
            'old_price' => null,
            'new_price' => 1000,
        ]);
    }
}
