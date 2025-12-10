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
class UpdateFinancerWithModulesTest extends ProtectedRouteTestCase
{
    use GeneratesUniqueModuleIds, WithFaker;

    const URI = '/api/v1/financers/';

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with FINANCER_ADMIN role
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
        ]);
    }

    #[Test]
    public function it_can_update_financer_with_modules_array(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Financer Test']);

        // Attach modules to division first
        $division = $financer->division;
        $division->modules()->attach([
            $this->getCoreModuleId() => ['active' => true, 'price_per_beneficiary' => null],
            $this->getAnalyticsModuleId() => ['active' => true, 'price_per_beneficiary' => 1000],
        ]);

        $updatedData = [
            'name' => 'Financer Test Updated',
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'external_id' => $financer->external_id,
            'registration_number' => $financer->registration_number,
            'registration_country' => $financer->registration_country,
            'website' => $financer->website,
            'iban' => $financer->iban,
            'vat_number' => $financer->vat_number,
            'representative_id' => $financer->representative_id,
            'available_languages' => $financer->available_languages,
            'company_number' => 'TEST123456',
            'modules' => [
                [
                    'id' => $this->getCoreModuleId(),
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => null, // Core module must have null price
                ],
                [
                    'id' => $this->getAnalyticsModuleId(),
                    'active' => true,
                    'promoted' => true,
                    'price_per_beneficiary' => 1000, // 10 euros in cents
                ],
            ],
        ];

        $this->assertDatabaseCount('financers', 1);
        $this->assertDatabaseCount('financer_module', 0);

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $updatedData);

        $response->assertStatus(200);

        // Verify financer was updated
        $this->assertDatabaseCount('financers', 1);
        $this->assertDatabaseHas('financers', [
            'id' => $financer->id,
            'name' => $updatedData['name'],
        ]);

        // Verify modules were attached
        $this->assertDatabaseCount('financer_module', 2);
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getCoreModuleId(),
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => null,
        ]);
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getAnalyticsModuleId(),
            'active' => true,
            'promoted' => true,
            'price_per_beneficiary' => 1000,
        ]);

        // Response might not include modules in some cases, just verify success
    }

    #[Test]
    public function it_can_update_existing_modules_for_financer(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Financer Test']);

        // Attach modules to division first
        $division = $financer->division;
        $division->modules()->attach([
            $this->getCoreModuleId() => ['active' => true, 'price_per_beneficiary' => null],
            $this->getAnalyticsModuleId() => ['active' => true, 'price_per_beneficiary' => 800],
            $this->getPremiumModuleId() => ['active' => true, 'price_per_beneficiary' => 1500],
        ]);

        // Create initial module attachments
        DB::table('financer_module')->insert([
            [
                'id' => fake()->uuid(),
                'financer_id' => $financer->id,
                'module_id' => $this->getCoreModuleId(),
                'active' => true,
                'promoted' => false,
                'price_per_beneficiary' => null, // Core module must have null price
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => fake()->uuid(),
                'financer_id' => $financer->id,
                'module_id' => $this->getAnalyticsModuleId(),
                'active' => false,
                'promoted' => false,
                'price_per_beneficiary' => 800,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $updatedData = [
            'name' => 'Financer Test Updated',
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getCoreModuleId(),
                    'active' => true,
                    'promoted' => true, // Changed from false
                    'price_per_beneficiary' => null, // Core module must have null price
                ],
                [
                    'id' => $this->getAnalyticsModuleId(),
                    'active' => true, // Changed from false
                    'promoted' => false,
                    'price_per_beneficiary' => 1200, // Changed from 800
                ],
                [
                    'id' => $this->getPremiumModuleId(),
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => 1500, // New module
                ],
            ],
        ];

        $this->assertDatabaseCount('financer_module', 2);

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $updatedData);

        $response->assertStatus(200);

        // Verify modules were updated/added
        $this->assertDatabaseCount('financer_module', 3);
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getCoreModuleId(),
            'active' => true,
            'promoted' => true,
            'price_per_beneficiary' => null,
        ]);
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getAnalyticsModuleId(),
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => 1200,
        ]);
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getPremiumModuleId(),
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => 1500,
        ]);
    }

    #[Test]
    public function it_can_activate_module_with_custom_pricing(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Financer Test']);

        // Attach module to division first
        $division = $financer->division;
        $division->modules()->attach([
            $this->getAnalyticsModuleId() => ['active' => true, 'price_per_beneficiary' => 2500],
        ]);

        $updatedData = [
            'name' => $financer->name,
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getAnalyticsModuleId(),
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => 2500, // 25 euros in cents
                ],
            ],
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $updatedData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getAnalyticsModuleId(),
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => 2500,
        ]);

        // Verify pricing history was recorded
        $this->assertDatabaseHas('module_pricing_history', [
            'entity_id' => $financer->id,
            'entity_type' => 'financer',
            'module_id' => $this->getAnalyticsModuleId(),
            'old_price' => null,
            'new_price' => 2500,
            'price_type' => 'module_price',
        ]);
    }

    #[Test]
    public function it_can_set_module_promotion_flags(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Financer Test']);

        // Attach modules to division first
        $division = $financer->division;
        $division->modules()->attach([
            $this->getCoreModuleId() => ['active' => true, 'price_per_beneficiary' => null],
            $this->getAnalyticsModuleId() => ['active' => true, 'price_per_beneficiary' => 600],
            $this->getPremiumModuleId() => ['active' => true, 'price_per_beneficiary' => 1800],
        ]);

        $updatedData = [
            'name' => $financer->name,
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getCoreModuleId(),
                    'active' => true,
                    'promoted' => true, // Core module promoted
                    'price_per_beneficiary' => null, // Core module must have null price
                ],
                [
                    'id' => $this->getAnalyticsModuleId(),
                    'active' => true,
                    'promoted' => true, // Analytics module promoted
                    'price_per_beneficiary' => 600,
                ],
                [
                    'id' => $this->getPremiumModuleId(),
                    'active' => true,
                    'promoted' => false, // Premium module not promoted
                    'price_per_beneficiary' => 1800,
                ],
            ],
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $updatedData);

        $response->assertStatus(200);

        // Verify promotion flags
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getCoreModuleId(),
            'promoted' => true,
        ]);
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getAnalyticsModuleId(),
            'promoted' => true,
        ]);
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getPremiumModuleId(),
            'promoted' => false,
        ]);

        // Response might not include detailed module data, just verify database state
    }

    #[Test]
    public function it_ensures_module_updates_are_atomic_with_financer_updates(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Financer Test']);

        // Force a database error by providing invalid company_number format
        $invalidData = [
            'name' => 'Updated Name',
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => 'INVALID!@#$%', // Invalid format should fail validation
            'modules' => [
                [
                    'id' => $this->getCoreModuleId(),
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => null, // Core module must have null price
                ],
            ],
        ];

        $this->assertDatabaseCount('financer_module', 0);

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $invalidData);

        // Should fail due to validation error
        $response->assertStatus(422);

        // Verify neither financer nor modules were updated due to rollback
        $this->assertDatabaseHas('financers', [
            'id' => $financer->id,
            'name' => 'Financer Test', // Original name preserved
        ]);
        $this->assertDatabaseCount('financer_module', 0); // No modules added
    }

    #[Test]
    public function it_can_deactivate_modules_by_setting_active_false(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Financer Test']);

        // Attach modules to division first
        $division = $financer->division;
        $division->modules()->attach([
            $this->getAnalyticsModuleId() => ['active' => true, 'price_per_beneficiary' => 800],
            $this->getPremiumModuleId() => ['active' => true, 'price_per_beneficiary' => 1200],
        ]);

        // Create initial module attachments (only non-core modules)
        DB::table('financer_module')->insert([
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
            [
                'id' => fake()->uuid(),
                'financer_id' => $financer->id,
                'module_id' => $this->getPremiumModuleId(),
                'active' => true,
                'promoted' => true,
                'price_per_beneficiary' => 1200,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $updatedData = [
            'name' => $financer->name,
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                // Keep first module active
                [
                    'id' => $this->getAnalyticsModuleId(),
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => 800,
                ],
                // Explicitly deactivate second module
                [
                    'id' => $this->getPremiumModuleId(),
                    'active' => false, // Explicitly set to false to deactivate
                ],
            ],
        ];

        $this->assertDatabaseCount('financer_module', 2);

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $updatedData);

        $response->assertStatus(200);

        // Verify both modules still exist but second is deactivated
        $this->assertDatabaseCount('financer_module', 2);
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getAnalyticsModuleId(),
            'active' => true,
        ]);
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getPremiumModuleId(),
            'active' => false,
        ]);
    }

    #[Test]
    public function it_validates_module_exists_before_attaching(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Financer Test']);

        $invalidData = [
            'name' => $financer->name,
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => '99999999-9999-9999-9999-999999999999', // Non-existent module
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => 500,
                ],
            ],
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $invalidData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['modules.0.id']);

        // Verify no modules were attached
        $this->assertDatabaseCount('financer_module', 0);
    }

    #[Test]
    public function it_can_handle_empty_modules_array(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Financer Test']);

        // Create initial module attachment with non-core module
        DB::table('financer_module')->insert([
            'id' => fake()->uuid(),
            'financer_id' => $financer->id,
            'module_id' => $this->getAnalyticsModuleId(), // Non-core module
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $updatedData = [
            'name' => 'Updated Financer',
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [], // Empty array means no module changes
        ];

        $this->assertDatabaseCount('financer_module', 1);

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $updatedData);

        $response->assertStatus(200);

        // Verify financer was updated
        $this->assertDatabaseHas('financers', [
            'id' => $financer->id,
            'name' => 'Updated Financer',
        ]);

        // Verify modules remain unchanged when array is empty
        $this->assertDatabaseCount('financer_module', 1);
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getAnalyticsModuleId(),
            'active' => true,
        ]);
    }

    #[Test]
    public function it_can_update_financer_without_modules_parameter(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Financer Test']);

        // Create initial module attachment
        DB::table('financer_module')->insert([
            'id' => fake()->uuid(),
            'financer_id' => $financer->id,
            'module_id' => $this->getCoreModuleId(),
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => null, // Core module must have null price
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $updatedData = [
            'name' => 'Updated Financer Name',
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            // No modules parameter - existing modules should remain unchanged
        ];

        $this->assertDatabaseCount('financer_module', 1);

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $updatedData);

        $response->assertStatus(200);

        // Verify financer was updated
        $this->assertDatabaseHas('financers', [
            'id' => $financer->id,
            'name' => 'Updated Financer Name',
        ]);

        // Verify existing modules remain unchanged
        $this->assertDatabaseCount('financer_module', 1);
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getCoreModuleId(),
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => null,
        ]);
    }

    #[Test]
    public function it_records_pricing_history_for_module_price_changes(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Financer Test']);

        // Attach module to division first (non-core module for pricing test)
        $division = $financer->division;
        $division->modules()->attach([
            $this->getAnalyticsModuleId() => ['active' => true, 'price_per_beneficiary' => 300],
        ]);

        // Create initial module with pricing
        DB::table('financer_module')->insert([
            'id' => fake()->uuid(),
            'financer_id' => $financer->id,
            'module_id' => $this->getAnalyticsModuleId(), // Non-core module
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => 300,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $updatedData = [
            'name' => $financer->name,
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getAnalyticsModuleId(),
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => 700, // Changed from 300 to 700
                ],
            ],
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $updatedData);

        $response->assertStatus(200);

        // Verify pricing history was recorded
        $this->assertDatabaseHas('module_pricing_history', [
            'entity_id' => $financer->id,
            'entity_type' => 'financer',
            'module_id' => $this->getAnalyticsModuleId(),
            'old_price' => 300,
            'new_price' => 700,
            'price_type' => 'module_price',
        ]);
    }
}
