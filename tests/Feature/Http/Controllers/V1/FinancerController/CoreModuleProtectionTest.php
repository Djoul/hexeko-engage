<?php

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use App\Enums\IDP\RoleDefaults;
use DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Traits\GeneratesUniqueModuleIds;
use Tests\ProtectedRouteTestCase;

/**
 * Integration tests for module management in financer API endpoint.
 * Tests the complete API flow including validation, authorization, and database persistence.
 *
 * For unit validation tests, see: Tests\Unit\Requests\FinancerFormRequestModulesTest
 */
#[Group('financer')]
#[Group('module')]
class CoreModuleProtectionTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions, GeneratesUniqueModuleIds;

    const URI = '/api/v1/financers/';

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with FINANCER_ADMIN role
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);

        // Create test modules
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
    public function it_prevents_deactivating_core_modules_in_api_update(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Test Financer']);

        // First attach core module as active
        DB::table('financer_module')->insert([
            'id' => fake()->uuid(),
            'financer_id' => $financer->id,
            'module_id' => $this->getCoreModuleId(), // Core module
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $updateData = [
            'name' => $financer->name,
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getCoreModuleId(),
                    'active' => false, // Trying to deactivate core module
                    'promoted' => false,
                    'price_per_beneficiary' => null,
                ],
            ],
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $updateData);

        // Should return 422 validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['modules.0.active'])
            ->assertJsonFragment([
                'modules.0.active' => ['Core module cannot be deactivated'],
            ]);

        // Verify core module remains active in database
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getCoreModuleId(),
            'active' => true,
        ]);
    }

    #[Test]
    public function it_prevents_setting_pricing_on_core_modules_in_api(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Test Financer']);

        $updateData = [
            'name' => $financer->name,
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getCoreModuleId(),
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => 500, // Trying to set price on core module
                ],
            ],
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $updateData);

        // Should return 422 validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['modules.0.price_per_beneficiary'])
            ->assertJsonFragment([
                'modules.0.price_per_beneficiary' => ['Core module price must always be null (included in core package price)'],
            ]);

        // Verify no new module was attached (transaction rolled back due to validation error)
        $this->assertDatabaseMissing('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getCoreModuleId(),
        ]);
    }

    #[Test]
    public function it_ensures_core_modules_remain_active_when_trying_to_set_active_false(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Test Financer']);

        // Attach core module as active initially
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

        $updateData = [
            'name' => 'Updated Financer Name',
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getCoreModuleId(),
                    'active' => false, // Attempting to deactivate
                    'promoted' => true,
                    'price_per_beneficiary' => null,
                ],
                [
                    'id' => $this->getAnalyticsModuleId(),
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => 1000,
                ],
            ],
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $updateData);

        // Should return validation error for core module deactivation
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['modules.0.active']);

        // Verify core module remains active and financer name unchanged due to validation failure
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getCoreModuleId(),
            'active' => true,
        ]);

        $this->assertDatabaseHas('financers', [
            'id' => $financer->id,
            'name' => 'Test Financer', // Original name preserved due to validation failure
        ]);

        // Regular module should not be added due to transaction rollback
        $this->assertDatabaseMissing('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getAnalyticsModuleId(),
        ]);
    }

    #[Test]
    public function it_allows_activating_core_modules_with_null_pricing(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Test Financer']);

        // Activate module in division first
        $division = $financer->division;
        $division->modules()->attach([
            $this->getCoreModuleId() => ['active' => true, 'price_per_beneficiary' => null],
        ]);

        $updateData = [
            'name' => $financer->name,
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getCoreModuleId(),
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => null, // Correct: null pricing for core module
                ],
            ],
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $updateData);

        // Should succeed
        $response->assertStatus(200);

        // Verify core module was attached correctly
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getCoreModuleId(),
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => null,
        ]);
    }

    #[Test]
    public function it_allows_updating_non_core_modules_normally(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Test Financer']);

        // Activate modules in division first
        $division = $financer->division;
        $division->modules()->attach([
            $this->getAnalyticsModuleId() => ['active' => true, 'price_per_beneficiary' => 1000],
            $this->getPremiumModuleId() => ['active' => true, 'price_per_beneficiary' => 2000],
        ]);

        $updateData = [
            'name' => $financer->name,
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getAnalyticsModuleId(), // Non-core module
                    'active' => false, // Can deactivate non-core
                    'promoted' => false,
                    'price_per_beneficiary' => 1500, // Can set price on non-core
                ],
                [
                    'id' => $this->getPremiumModuleId(), // Non-core module
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => 2000, // Keep existing price
                ],
            ],
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $updateData);

        // Should succeed
        $response->assertStatus(200);

        // Verify non-core modules were set correctly
        // When module is deactivated, price is set to null
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getAnalyticsModuleId(),
            'active' => false,
            'promoted' => false,
            'price_per_beneficiary' => null, // Price is nullified when deactivated
        ]);

        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getPremiumModuleId(),
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => 2000,
        ]);
    }

    #[Test]
    public function it_validates_core_module_protection_with_mixed_modules(): void
    {
        // Use the authenticated user's financer
        $financer = $this->auth->financers->first();
        $financer->update(['name' => 'Test Financer']);

        $updateData = [
            'name' => $financer->name,
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getCoreModuleId(), // Core module
                    'active' => false, // Invalid: trying to deactivate core
                    'promoted' => false,
                    'price_per_beneficiary' => null,
                ],
                [
                    'id' => $this->getAnalyticsModuleId(), // Non-core module
                    'active' => true,
                    'promoted' => true,
                    'price_per_beneficiary' => 2000, // Valid for non-core
                ],
            ],
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $updateData);

        // Should return validation error only for core module
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['modules.0.active'])
            ->assertJsonMissingValidationErrors(['modules.1.active', 'modules.1.price_per_beneficiary']);

        // Verify no modules were attached due to validation failure
        $this->assertDatabaseMissing('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getCoreModuleId(),
        ]);
        $this->assertDatabaseMissing('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $this->getAnalyticsModuleId(),
        ]);
    }
}
