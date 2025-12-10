<?php

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use App\Enums\IDP\RoleDefaults;
use DB;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Traits\GeneratesUniqueModuleIds;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['financers', 'modules', 'financer_module', 'module_pricing_history'], scope: 'test')]
#[Group('financer')]
#[Group('module')]
class ModuleTransactionTest extends ProtectedRouteTestCase
{
    use GeneratesUniqueModuleIds, WithFaker;

    const URI = '/api/v1/financers/';

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with UPDATE_FINANCER permission
        // Using FINANCER_ADMIN role which should have UPDATE_FINANCER permission
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);

        // Create test modules for module assignment tests
        DB::table('modules')->insert([
            [
                'id' => $this->getCoreModuleId(),
                'name' => json_encode(['en' => 'Core Module', 'fr' => 'Module Core']),
                'description' => json_encode(['en' => 'Core functionality', 'fr' => 'Fonctionnalité principale']),
                'category' => 'enterprise_life',
                'is_core' => true,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $this->getAnalyticsModuleId(),
                'name' => json_encode(['en' => 'Analytics Module', 'fr' => 'Module Analytics']),
                'description' => json_encode(['en' => 'Advanced analytics', 'fr' => 'Analytics avancées']),
                'category' => 'analytics',
                'is_core' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $this->getPremiumModuleId(),
                'name' => json_encode(['en' => 'Premium Module', 'fr' => 'Module Premium']),
                'description' => json_encode(['en' => 'Premium features', 'fr' => 'Fonctionnalités premium']),
                'category' => 'premium',
                'is_core' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => '01946c4e-7004-7000-8000-000000000004',
                'name' => json_encode(['en' => 'Invalid Module', 'fr' => 'Module Invalide']),
                'description' => json_encode(['en' => 'Test invalid module', 'fr' => 'Module test invalide']),
                'category' => 'test',
                'is_core' => false,
                'active' => false, // Inactive module for testing
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    #[Test]
    public function it_rolls_back_all_changes_when_financer_update_fails_but_modules_are_valid(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Original Financer']);

        // Get initial counts
        $initialFinancerCount = DB::table('financers')->count();
        $initialModuleCount = DB::table('financer_module')->count();
        $initialPricingHistoryCount = DB::table('module_pricing_history')->count();

        // Create invalid data that will cause financer update to fail
        $invalidData = [
            'name' => 'Updated Name',
            'timezone' => $financer->timezone,
            'division_id' => '99999999-9999-9999-9999-999999999999', // Invalid division_id
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getAnalyticsModuleId(),
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => 500,
                ],
                [
                    'id' => $this->getPremiumModuleId(),
                    'active' => true,
                    'promoted' => true,
                    'price_per_beneficiary' => 1000,
                ],
            ],
        ];

        $this->assertEquals(0, $initialModuleCount);

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $invalidData);

        // Should fail due to invalid division_id
        $response->assertStatus(422);

        // Verify database state is unchanged (atomic rollback)
        $this->assertEquals($initialFinancerCount, DB::table('financers')->count());
        $this->assertEquals($initialModuleCount, DB::table('financer_module')->count());
        $this->assertEquals($initialPricingHistoryCount, DB::table('module_pricing_history')->count());

        // Verify financer was not updated
        $this->assertDatabaseHas('financers', [
            'id' => $financer->id,
            'name' => 'Original Financer', // Original name preserved
            'division_id' => $financer->division_id, // Original division preserved
        ]);

        // Verify no modules were attached
        $this->assertDatabaseMissing('financer_module', [
            'financer_id' => $financer->id,
        ]);

        // Verify no pricing history was created
        $this->assertDatabaseMissing('module_pricing_history', [
            'entity_id' => $financer->id,
            'entity_type' => 'financer',
        ]);
    }

    #[Test]
    public function it_rolls_back_all_changes_when_some_modules_are_valid_and_some_invalid(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Test Financer']);

        // Get initial counts
        $initialFinancerCount = DB::table('financers')->count();
        $initialModuleCount = DB::table('financer_module')->count();
        $initialPricingHistoryCount = DB::table('module_pricing_history')->count();

        // Mix valid and invalid modules
        $mixedData = [
            'name' => 'Updated Financer Name',
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getAnalyticsModuleId(), // Valid module
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => 500,
                ],
                [
                    'id' => '99999999-9999-9999-9999-999999999999', // Invalid module ID
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => 1000,
                ],
                [
                    'id' => $this->getPremiumModuleId(), // Valid module
                    'active' => true,
                    'promoted' => true,
                    'price_per_beneficiary' => 1500,
                ],
            ],
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $mixedData);

        // Should fail due to invalid module ID
        $response->assertStatus(422);

        // Verify atomic rollback - no changes to any table
        $this->assertEquals($initialFinancerCount, DB::table('financers')->count());
        $this->assertEquals($initialModuleCount, DB::table('financer_module')->count());
        $this->assertEquals($initialPricingHistoryCount, DB::table('module_pricing_history')->count());

        // Verify financer was not updated despite being valid
        $this->assertDatabaseHas('financers', [
            'id' => $financer->id,
            'name' => 'Test Financer', // Original name preserved
        ]);

        // Verify no modules were attached (even valid ones)
        $this->assertDatabaseMissing('financer_module', [
            'financer_id' => $financer->id,
        ]);

        // Verify no pricing history was created
        $this->assertEquals(0, DB::table('module_pricing_history')
            ->where('entity_id', $financer->id)
            ->where('entity_type', 'financer')
            ->count());
    }

    #[Test]
    public function it_rolls_back_when_database_constraint_violation_occurs_during_module_processing(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Constraint Test Financer']);

        // Create existing module attachment to force constraint violation
        DB::table('financer_module')->insert([
            'id' => fake()->uuid(),
            'financer_id' => $financer->id,
            'module_id' => $this->getAnalyticsModuleId(),
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => 300,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Get initial counts
        $initialFinancerCount = DB::table('financers')->count();
        $initialModuleCount = DB::table('financer_module')->count();
        $initialPricingHistoryCount = DB::table('module_pricing_history')->count();

        // Attempt to create data that could cause constraint violations in the module pivot table
        $constraintViolationData = [
            'name' => 'Updated Name That Should Be Rolled Back',
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getAnalyticsModuleId(), // Existing module
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => -1, // Invalid negative price that should fail validation
                ],
            ],
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $constraintViolationData);

        // Should fail due to constraint/validation error
        $response->assertStatus(422);

        // Verify atomic rollback - counts should remain the same
        $this->assertEquals($initialFinancerCount, DB::table('financers')->count());
        $this->assertEquals($initialModuleCount, DB::table('financer_module')->count());
        $this->assertEquals($initialPricingHistoryCount, DB::table('module_pricing_history')->count());

        // Verify financer was not updated
        $this->assertDatabaseHas('financers', [
            'id' => $financer->id,
            'name' => 'Constraint Test Financer', // Original name preserved
        ]);

        // Verify existing module attachment was not modified
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getAnalyticsModuleId(),
            'price_per_beneficiary' => 300, // Original price preserved
        ]);
    }

    #[Test]
    public function it_successfully_updates_module_pricing(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Pricing Update Test']);

        // Create existing module with pricing
        DB::table('financer_module')->insert([
            'id' => fake()->uuid(),
            'financer_id' => $financer->id,
            'module_id' => $this->getAnalyticsModuleId(),
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Get initial counts
        $initialFinancerCount = DB::table('financers')->count();
        $initialModuleCount = DB::table('financer_module')->count();

        // Update with price change
        $dataWithPriceChange = [
            'name' => 'Updated Name For Pricing Test',
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getAnalyticsModuleId(),
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => 1000, // Price change
                ],
            ],
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $dataWithPriceChange);

        // Should succeed
        $response->assertStatus(200);

        // Verify counts remain the same (update, not insert)
        $this->assertEquals($initialFinancerCount, DB::table('financers')->count());
        $this->assertEquals($initialModuleCount, DB::table('financer_module')->count());

        // Verify financer was updated
        $this->assertDatabaseHas('financers', [
            'id' => $financer->id,
            'name' => 'Updated Name For Pricing Test',
        ]);

        // Verify module pricing was updated
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getAnalyticsModuleId(),
            'price_per_beneficiary' => 1000, // New price
        ]);
    }

    #[Test]
    public function it_handles_complete_transaction_rollback_with_multiple_operations(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Multi Operation Test']);

        // Create initial modules to simulate complex update scenario
        DB::table('financer_module')->insert([
            [
                'id' => fake()->uuid(),
                'financer_id' => $financer->id,
                'module_id' => $this->getCoreModuleId(),
                'active' => true,
                'promoted' => false,
                'price_per_beneficiary' => null, // Core module has no price
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => fake()->uuid(),
                'financer_id' => $financer->id,
                'module_id' => $this->getAnalyticsModuleId(),
                'active' => true,
                'promoted' => false,
                'price_per_beneficiary' => 800,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Add some pricing history
        DB::table('module_pricing_history')->insert([
            'id' => fake()->uuid(),
            'entity_id' => $financer->id,
            'entity_type' => 'financer',
            'module_id' => $this->getAnalyticsModuleId(),
            'old_price' => null,
            'new_price' => 800,
            'price_type' => 'module_price',
            'reason' => 'Initial pricing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Get initial counts
        $initialFinancerCount = DB::table('financers')->count();
        $initialModuleCount = DB::table('financer_module')->count();
        $initialPricingHistoryCount = DB::table('module_pricing_history')->count();

        // Complex update that should fail midway
        $complexUpdateData = [
            'name' => 'Updated Complex Name',
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getCoreModuleId(), // Core module - should stay
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => null,
                ],
                [
                    'id' => $this->getAnalyticsModuleId(), // Update existing
                    'active' => true,
                    'promoted' => true,
                    'price_per_beneficiary' => 1200, // Price change
                ],
                [
                    'id' => $this->getPremiumModuleId(), // Add new module
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => 1500,
                ],
                [
                    'id' => '99999999-9999-9999-9999-999999999999', // Invalid module - should cause failure
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => 2000,
                ],
            ],
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $complexUpdateData);

        // Should fail due to invalid module
        $response->assertStatus(422);

        // Verify complete atomic rollback
        $this->assertEquals($initialFinancerCount, DB::table('financers')->count());
        $this->assertEquals($initialModuleCount, DB::table('financer_module')->count());
        $this->assertEquals($initialPricingHistoryCount, DB::table('module_pricing_history')->count());

        // Verify financer was not updated
        $this->assertDatabaseHas('financers', [
            'id' => $financer->id,
            'name' => 'Multi Operation Test', // Original name preserved
        ]);

        // Verify existing module attachments were not modified
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getAnalyticsModuleId(),
            'price_per_beneficiary' => 800, // Original price preserved
            'promoted' => false, // Original promotion status preserved
        ]);

        // Verify no new modules were added
        $this->assertDatabaseMissing('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getPremiumModuleId(),
        ]);

        // Verify no new pricing history was added
        $currentPricingHistoryCount = DB::table('module_pricing_history')
            ->where('entity_id', $financer->id)
            ->where('entity_type', 'financer')
            ->count();
        $this->assertEquals(1, $currentPricingHistoryCount); // Only the initial one
    }

    #[Test]
    public function it_successfully_commits_all_changes_when_transaction_succeeds(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Success Test Financer']);

        // Activate modules in the division first
        $division = $financer->division;
        $division->modules()->attach([
            $this->getAnalyticsModuleId() => ['active' => true, 'price_per_beneficiary' => 1000],
            $this->getPremiumModuleId() => ['active' => true, 'price_per_beneficiary' => 2000],
        ]);

        // Get initial counts
        $initialFinancerCount = DB::table('financers')->count();
        $initialModuleCount = DB::table('financer_module')->count();
        DB::table('module_pricing_history')->count();

        // Valid data that should succeed completely
        $validData = [
            'name' => 'Successfully Updated Financer',
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getAnalyticsModuleId(),
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => 600,
                ],
                [
                    'id' => $this->getPremiumModuleId(),
                    'active' => true,
                    'promoted' => true,
                    'price_per_beneficiary' => 1200,
                ],
            ],
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $validData);

        // Should succeed
        $response->assertStatus(200)->assertJsonMissingValidationErrors();

        // Verify all changes were committed
        $this->assertEquals($initialFinancerCount, DB::table('financers')->count()); // No new financers
        $this->assertEquals($initialModuleCount + 2, DB::table('financer_module')->count()); // 2 modules added

        // Verify financer was updated
        $this->assertDatabaseHas('financers', [
            'id' => $financer->id,
            'name' => 'Successfully Updated Financer',
        ]);

        // Verify modules were attached correctly
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getAnalyticsModuleId(),
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => 600,
        ]);

        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getPremiumModuleId(),
            'active' => true,
            'promoted' => true,
            'price_per_beneficiary' => 1200,
        ]);

        // Verify pricing history was created for new modules
        $this->assertDatabaseHas('module_pricing_history', [
            'entity_id' => $financer->id,
            'entity_type' => 'financer',
            'module_id' => $this->getAnalyticsModuleId(),
            'old_price' => null,
            'new_price' => 600,
            'price_type' => 'module_price',
        ]);

        $this->assertDatabaseHas('module_pricing_history', [
            'entity_id' => $financer->id,
            'entity_type' => 'financer',
            'module_id' => $this->getPremiumModuleId(),
            'old_price' => null,
            'new_price' => 1200,
            'price_type' => 'module_price',
        ]);
    }

    #[Test]
    public function it_maintains_database_consistency_across_failed_partial_updates(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Consistency Test']);

        // Create some existing state
        DB::table('financer_module')->insert([
            'id' => fake()->uuid(),
            'financer_id' => $financer->id,
            'module_id' => $this->getCoreModuleId(),
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Record initial state for comparison
        $originalFinancer = DB::table('financers')->where('id', $financer->id)->first();
        $originalModules = DB::table('financer_module')->where('financer_id', $financer->id)->get();
        $originalPricingHistory = DB::table('module_pricing_history')
            ->where('entity_id', $financer->id)
            ->where('entity_type', 'financer')
            ->get();

        // Attempt update that will fail
        $partialUpdateData = [
            'name' => 'Partially Updated Name',
            'division_id' => '99999999-9999-9999-9999-999999999999', // Invalid division_id to force failure
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getAnalyticsModuleId(),
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => 700,
                ],
            ],
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $partialUpdateData);

        // Should fail due to invalid division_id
        $response->assertStatus(422);

        // Verify exact state restoration
        $currentFinancer = DB::table('financers')->where('id', $financer->id)->first();
        $currentModules = DB::table('financer_module')->where('financer_id', $financer->id)->get();
        $currentPricingHistory = DB::table('module_pricing_history')
            ->where('entity_id', $financer->id)
            ->where('entity_type', 'financer')
            ->get();

        // Compare all fields to ensure nothing changed
        $this->assertEquals($originalFinancer->name, $currentFinancer->name);
        $this->assertEquals($originalFinancer->division_id, $currentFinancer->division_id);
        $this->assertEquals($originalFinancer->updated_at, $currentFinancer->updated_at);

        // Verify module attachments are identical
        $this->assertCount($originalModules->count(), $currentModules);
        foreach ($originalModules as $index => $originalModule) {
            $currentModule = $currentModules[$index];
            $this->assertEquals($originalModule->module_id, $currentModule->module_id);
            $this->assertEquals($originalModule->active, $currentModule->active);
            $this->assertEquals($originalModule->promoted, $currentModule->promoted);
            $this->assertEquals($originalModule->price_per_beneficiary, $currentModule->price_per_beneficiary);
        }

        // Verify pricing history is identical
        $this->assertCount($originalPricingHistory->count(), $currentPricingHistory);
    }
}
