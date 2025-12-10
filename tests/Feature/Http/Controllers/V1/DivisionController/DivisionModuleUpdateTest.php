<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\DivisionController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Module;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('module')]
#[Group('division')]
class DivisionModuleUpdateTest extends ProtectedRouteTestCase
{
    protected bool $checkAuth = false;

    protected bool $checkPermissions = false;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);
        $this->hydrateAuthorizationContext($this->admin);
    }

    #[Test]
    public function it_updates_division_with_module_activation_and_pricing(): void
    {
        // Arrange
        $division = ModelFactory::createDivision([
            'name' => 'Original Division',
            'country' => 'FR',
            'currency' => 'EUR',
            'timezone' => 'Europe/Paris',
            'language' => 'fr-FR',
        ]);

        $module1 = Module::factory()->create(['is_core' => false]);
        $module2 = Module::factory()->create(['is_core' => false]);

        // Act
        $response = $this->actingAs($this->admin)
            ->putJson('/api/v1/divisions/'.$division->id, [
                'name' => 'Updated Division',
                'country' => 'FR',
                'currency' => 'EUR',
                'timezone' => 'Europe/Paris',
                'language' => 'fr-FR',
                'modules' => [
                    [
                        'id' => $module1->id,
                        'active' => true,
                        'price_per_beneficiary' => 1500,
                    ],
                    [
                        'id' => $module2->id,
                        'active' => true,
                        'price_per_beneficiary' => 2000,
                    ],
                ],
            ]);

        // Assert
        $response->assertOk();

        $this->assertDatabaseHas('divisions', [
            'id' => $division->id,
            'name' => 'Updated Division',
        ]);

        $this->assertDatabaseHas('division_module', [
            'division_id' => $division->id,
            'module_id' => $module1->id,
            'active' => true,
            'price_per_beneficiary' => 1500,
        ]);

        $this->assertDatabaseHas('division_module', [
            'division_id' => $division->id,
            'module_id' => $module2->id,
            'active' => true,
            'price_per_beneficiary' => 2000,
        ]);
    }

    #[Test]
    public function it_updates_division_without_modules_for_backward_compatibility(): void
    {
        // Arrange
        $division = ModelFactory::createDivision([
            'name' => 'Original Division',
            'country' => 'FR',
            'currency' => 'EUR',
            'timezone' => 'Europe/Paris',
            'language' => 'fr-FR',
        ]);

        $module = Module::factory()->create(['is_core' => false]);

        // Attach a module to the division
        $division->modules()->attach($module->id, [
            'active' => true,
            'price_per_beneficiary' => 1000,
        ]);

        // Act
        $response = $this->actingAs($this->admin)
            ->putJson('/api/v1/divisions/'.$division->id, [
                'name' => 'Updated Division Only',
                'country' => 'BE',
                'currency' => 'EUR',
                'timezone' => 'Europe/Brussels',
                'language' => 'fr-FR',
                // No modules array provided
            ]);

        // Assert
        $response->assertOk();

        $this->assertDatabaseHas('divisions', [
            'id' => $division->id,
            'name' => 'Updated Division Only',
            'country' => 'BE',
            'timezone' => 'Europe/Brussels',
        ]);

        // Module should remain unchanged
        $this->assertDatabaseHas('division_module', [
            'division_id' => $division->id,
            'module_id' => $module->id,
            'active' => true,
            'price_per_beneficiary' => 1000,
        ]);
    }

    #[Test]
    public function it_deactivates_non_core_modules(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $module = Module::factory()->create(['is_core' => false]);

        // Activate module first
        $division->modules()->attach($module->id, [
            'active' => true,
            'price_per_beneficiary' => 1500,
        ]);

        // Act
        $response = $this->actingAs($this->admin)
            ->putJson('/api/v1/divisions/'.$division->id, [
                'name' => $division->name,
                'country' => $division->country,
                'currency' => $division->currency,
                'timezone' => $division->timezone,
                'language' => $division->language,
                'modules' => [
                    [
                        'id' => $module->id,
                        'active' => false,
                    ],
                ],
            ]);

        // Assert
        $response->assertOk();

        $this->assertDatabaseHas('division_module', [
            'division_id' => $division->id,
            'module_id' => $module->id,
            'active' => false,
        ]);
    }

    #[Test]
    public function it_prevents_core_module_deactivation(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $coreModule = Module::factory()->create(['is_core' => true]);

        // Activate core module first
        $division->modules()->attach($coreModule->id, [
            'active' => true,
            'price_per_beneficiary' => null,
        ]);

        // Act
        $response = $this->actingAs($this->admin)
            ->putJson('/api/v1/divisions/'.$division->id, [
                'name' => $division->name,
                'country' => $division->country,
                'currency' => $division->currency,
                'timezone' => $division->timezone,
                'language' => $division->language,
                'modules' => [
                    [
                        'id' => $coreModule->id,
                        'active' => false,
                    ],
                ],
            ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['modules.0.active']);
        $response->assertJson([
            'errors' => [
                'modules.0.active' => ['Core module cannot be deactivated'],
            ],
        ]);
    }

    #[Test]
    public function it_prevents_non_null_price_on_core_modules(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $coreModule = Module::factory()->create(['is_core' => true]);

        // Act
        $response = $this->actingAs($this->admin)
            ->putJson('/api/v1/divisions/'.$division->id, [
                'name' => $division->name,
                'country' => $division->country,
                'currency' => $division->currency,
                'timezone' => $division->timezone,
                'language' => $division->language,
                'modules' => [
                    [
                        'id' => $coreModule->id,
                        'active' => true,
                        'price_per_beneficiary' => 1500, // Should not be allowed
                    ],
                ],
            ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['modules.0.price_per_beneficiary']);
        $response->assertJson([
            'errors' => [
                'modules.0.price_per_beneficiary' => ['Core module price must always be null (included in core package price)'],
            ],
        ]);
    }

    #[Test]
    public function it_handles_invalid_module_id(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $invalidModuleId = '550e8400-e29b-41d4-a716-446655440000'; // Non-existent UUID

        // Act
        $response = $this->actingAs($this->admin)
            ->putJson('/api/v1/divisions/'.$division->id, [
                'name' => $division->name,
                'country' => $division->country,
                'currency' => $division->currency,
                'timezone' => $division->timezone,
                'language' => $division->language,
                'modules' => [
                    [
                        'id' => $invalidModuleId,
                        'active' => true,
                        'price_per_beneficiary' => 1500,
                    ],
                ],
            ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['modules.0.id']);
    }

    #[Test]
    public function it_updates_module_prices_for_existing_modules(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $module = Module::factory()->create(['is_core' => false]);

        // Attach module with initial price
        $division->modules()->attach($module->id, [
            'active' => true,
            'price_per_beneficiary' => 1000,
        ]);

        // Act
        $response = $this->actingAs($this->admin)
            ->putJson('/api/v1/divisions/'.$division->id, [
                'name' => $division->name,
                'country' => $division->country,
                'currency' => $division->currency,
                'timezone' => $division->timezone,
                'language' => $division->language,
                'modules' => [
                    [
                        'id' => $module->id,
                        'active' => true,
                        'price_per_beneficiary' => 2500, // Update price
                    ],
                ],
            ]);

        // Assert
        $response->assertOk();

        $this->assertDatabaseHas('division_module', [
            'division_id' => $division->id,
            'module_id' => $module->id,
            'active' => true,
            'price_per_beneficiary' => 2500,
        ]);
    }

    #[Test]
    public function it_handles_mixed_core_and_non_core_modules(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $coreModule = Module::factory()->create(['is_core' => true]);
        $nonCoreModule = Module::factory()->create(['is_core' => false]);

        // Act
        $response = $this->actingAs($this->admin)
            ->putJson('/api/v1/divisions/'.$division->id, [
                'name' => $division->name,
                'country' => $division->country,
                'currency' => $division->currency,
                'timezone' => $division->timezone,
                'language' => $division->language,
                'core_package_price' => 5000,
                'modules' => [
                    [
                        'id' => $coreModule->id,
                        'active' => true,
                        'price_per_beneficiary' => null, // Correct for core module
                    ],
                    [
                        'id' => $nonCoreModule->id,
                        'active' => true,
                        'price_per_beneficiary' => 1500,
                    ],
                ],
            ]);

        // Assert
        $response->assertOk();

        $this->assertDatabaseHas('division_module', [
            'division_id' => $division->id,
            'module_id' => $coreModule->id,
            'active' => true,
            'price_per_beneficiary' => null,
        ]);

        $this->assertDatabaseHas('division_module', [
            'division_id' => $division->id,
            'module_id' => $nonCoreModule->id,
            'active' => true,
            'price_per_beneficiary' => 1500,
        ]);

        $this->assertDatabaseHas('divisions', [
            'id' => $division->id,
            'core_package_price' => 5000,
        ]);
    }

    #[Test]
    public function it_prevents_non_core_module_deactivation_if_enabled_in_at_least_one_financer(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $module = Module::factory()->create(['is_core' => false]); // NON-CORE module

        // Activate module for division
        $division->modules()->attach($module->id, [
            'active' => true,
            'price_per_beneficiary' => 1500,
        ]);

        // Create financer within this division
        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'Test Financer',
        ]);

        // Activate module for financer
        $financer->modules()->attach($module->id, [
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => null,
        ]);

        // Act - Try to deactivate module at division level
        $response = $this->actingAs($this->admin)
            ->putJson('/api/v1/divisions/'.$division->id.'/modules', [
                'core_package_price' => null,
                'modules' => [
                    [
                        'id' => $module->id,
                        'active' => false, // Trying to deactivate
                        'price_per_beneficiary' => null,
                    ],
                ],
            ]);

        // Assert - Should return 422 with validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['modules.0.active']);
        $response->assertJson([
            'errors' => [
                'modules.0.active' => ['Cannot deactivate module: it is currently enabled in 1 financer(s) of this division'],
            ],
        ]);

        // Verify module remains active in division
        $this->assertDatabaseHas('division_module', [
            'division_id' => $division->id,
            'module_id' => $module->id,
            'active' => true,
        ]);
    }

    #[Test]
    public function it_allows_module_deactivation_if_no_financer_uses_it(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $module = Module::factory()->create(['is_core' => false]);

        // Activate module for division
        $division->modules()->attach($module->id, [
            'active' => true,
            'price_per_beneficiary' => 1500,
        ]);

        // Create financer but DO NOT activate module for it
        ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'Test Financer Without Module',
        ]);

        // Act - Deactivate module at division level (should succeed)
        $response = $this->actingAs($this->admin)
            ->putJson('/api/v1/divisions/'.$division->id.'/modules', [
                'core_package_price' => null,
                'modules' => [
                    [
                        'id' => $module->id,
                        'active' => false,
                        'price_per_beneficiary' => null,
                    ],
                ],
            ]);

        // Assert - Should succeed
        $response->assertOk();

        // Verify module is deactivated
        $this->assertDatabaseHas('division_module', [
            'division_id' => $division->id,
            'module_id' => $module->id,
            'active' => false,
        ]);
    }

    #[Test]
    public function it_prevents_module_deactivation_with_multiple_financers_using_it(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $module = Module::factory()->create(['is_core' => false]);

        // Activate module for division
        $division->modules()->attach($module->id, [
            'active' => true,
            'price_per_beneficiary' => 1500,
        ]);

        // Create 3 financers, all using the module
        for ($i = 1; $i <= 3; $i++) {
            $financer = ModelFactory::createFinancer([
                'division_id' => $division->id,
                'name' => "Financer $i",
            ]);

            $financer->modules()->attach($module->id, [
                'active' => true,
                'promoted' => false,
                'price_per_beneficiary' => null,
            ]);
        }

        // Act - Try to deactivate module
        $response = $this->actingAs($this->admin)
            ->putJson('/api/v1/divisions/'.$division->id.'/modules', [
                'core_package_price' => null,
                'modules' => [
                    [
                        'id' => $module->id,
                        'active' => false,
                        'price_per_beneficiary' => null,
                    ],
                ],
            ]);

        // Assert - Should return 422 with count of financers
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['modules.0.active']);
        $response->assertJson([
            'errors' => [
                'modules.0.active' => ['Cannot deactivate module: it is currently enabled in 3 financer(s) of this division'],
            ],
        ]);
    }
}
