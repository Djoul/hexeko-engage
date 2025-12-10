<?php

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use App\Enums\IDP\RoleDefaults;
use DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Traits\GeneratesUniqueModuleIds;
use Tests\ProtectedRouteTestCase;

#[Group('financer')]
#[Group('module')]
class FinancerResourceModulesTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions, GeneratesUniqueModuleIds;

    const URI = '/api/v1/financers/';

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with FINANCER_ADMIN role
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);

        // Create test modules (mix of core and non-core)
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

        // Attach modules to division
        $division = $this->auth->financers->first()->division;
        $division->modules()->attach([
            $this->getCoreModuleId() => ['active' => true, 'price_per_beneficiary' => null],
            $this->getAnalyticsModuleId() => ['active' => true, 'price_per_beneficiary' => 1000],
            $this->getPremiumModuleId() => ['active' => true, 'price_per_beneficiary' => 2000],
        ]);
    }

    #[Test]
    public function it_returns_only_modules_active_in_financer_division(): void
    {
        $financer = $this->auth->financers->first();
        $division = $financer->division;

        // Create an additional module NOT active in the division
        $inactiveModuleId = $this->generateModuleId(3);
        DB::table('modules')->insert([
            'id' => $inactiveModuleId,
            'name' => json_encode(['en' => 'Inactive Module', 'fr' => 'Module Inactif']),
            'description' => json_encode(['en' => 'Not active in division', 'fr' => 'Pas actif dans la division']),
            'category' => 'inactive',
            'is_core' => false,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attach the inactive module to division with active=false
        $division->modules()->attach($inactiveModuleId, [
            'active' => false,
            'price_per_beneficiary' => 5000,
        ]);

        // Attach the inactive module to financer (should NOT appear in response)
        $financer->modules()->attach($inactiveModuleId, [
            'active' => true,
            'promoted' => true,
            'price_per_beneficiary' => 5000,
        ]);

        // Update financer with modules
        $updateData = [
            'name' => $financer->name,
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getCoreModuleId(), // Core module (should be filtered out)
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => null,
                ],
                [
                    'id' => $this->getAnalyticsModuleId(), // Active in division
                    'active' => true,
                    'promoted' => true,
                    'price_per_beneficiary' => 1500,
                ],
                [
                    'id' => $this->getPremiumModuleId(), // Active in division but inactive in financer
                    'active' => false,
                    'promoted' => false,
                    'price_per_beneficiary' => null,
                ],
                [
                    'id' => $inactiveModuleId, // Active in financer but INACTIVE in division (should NOT appear)
                    'active' => true,
                    'promoted' => true,
                    'price_per_beneficiary' => 5000,
                ],
            ],
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'modules' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'category',
                            'is_core',
                            'active',
                            'promoted',
                            'price_per_beneficiary',
                        ],
                    ],
                ],
            ]);

        // Check that only non-core modules active in division are returned
        $modules = $response->json('data.modules');
        $this->assertCount(2, $modules, 'Should return only 2 non-core modules active in division');

        // Verify the active non-core module data
        $analyticsModule = collect($modules)->firstWhere('id', $this->getAnalyticsModuleId());
        $this->assertNotNull($analyticsModule, 'Analytics module should be included (active in division)');
        $this->assertEquals(false, $analyticsModule['is_core']);
        $this->assertEquals(true, $analyticsModule['active']);
        $this->assertEquals(true, $analyticsModule['promoted']);
        $this->assertEquals(1500, $analyticsModule['price_per_beneficiary']);

        // Verify the Premium module shows financer status (active=false from financer)
        // Note: The 'active' field now reflects FINANCER pivot status
        $premiumModule = collect($modules)->firstWhere('id', $this->getPremiumModuleId());
        $this->assertNotNull($premiumModule, 'Premium module should be included (active in division)');
        $this->assertEquals(false, $premiumModule['is_core']);
        $this->assertEquals(false, $premiumModule['active']); // Shows financer pivot status (active=false)
        $this->assertEquals(false, $premiumModule['promoted']); // Shows financer pivot status
        $this->assertNull($premiumModule['price_per_beneficiary']); // Shows financer pivot status

        // Ensure core module is not in the response
        $coreModule = collect($modules)->firstWhere('id', $this->getCoreModuleId());
        $this->assertNull($coreModule, 'Core module should not be included in response');

        // Ensure module inactive in division is NOT in the response
        $inactiveModule = collect($modules)->firstWhere('id', $inactiveModuleId);
        $this->assertNull($inactiveModule, 'Module inactive in division should NOT be included, even if active in financer');
    }

    #[Test]
    public function it_does_not_include_modules_when_not_updated(): void
    {
        $financer = $this->auth->financers->first();

        // Update financer without modules parameter
        $updateData = [
            'name' => 'Updated Name Without Modules',
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $updateData);

        $response->assertStatus(200);

        // Modules should be null when not updated (relationship not loaded)
        $response->assertJsonPath('data.modules', null);
    }

    #[Test]
    public function it_returns_division_modules_as_inactive_when_only_core_modules_provided(): void
    {
        $financer = $this->auth->financers->first();

        // Update with only core module - non-core division modules should be returned as inactive
        $updateData = [
            'name' => $financer->name,
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'modules' => [
                [
                    'id' => $this->getCoreModuleId(), // Core module
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => null,
                ],
            ],
        ];

        $response = $this->actingAs($this->auth)->putJson(self::URI."{$financer->id}", $updateData);

        $response->assertStatus(200);

        // Should return division's non-core modules (Analytics and Premium) but as inactive
        $modules = $response->json('data.modules');
        $this->assertIsArray($modules);
        $this->assertCount(2, $modules, 'Should return 2 division-active non-core modules');

        // Both modules should be inactive since they weren't in the update request
        foreach ($modules as $module) {
            $this->assertFalse($module['active'], "Module {$module['id']} should be inactive");
            $this->assertFalse($module['promoted'], "Module {$module['id']} should not be promoted");
            $this->assertNull($module['price_per_beneficiary'], "Module {$module['id']} should have null price");
        }

        // Verify Analytics module exists but is inactive
        $analyticsModule = collect($modules)->firstWhere('id', $this->getAnalyticsModuleId());
        $this->assertNotNull($analyticsModule, 'Analytics module should be in response (division-active)');

        // Verify Premium module exists but is inactive
        $premiumModule = collect($modules)->firstWhere('id', $this->getPremiumModuleId());
        $this->assertNotNull($premiumModule, 'Premium module should be in response (division-active)');
    }
}
