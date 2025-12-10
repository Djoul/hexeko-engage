<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use App\Models\Module;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('financer')]
#[Group('module')]
class BackwardCompatibilityTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_updates_financer_without_modules_parameter(): void
    {
        // Arrange: Create test data
        $division = ModelFactory::createDivision([
            'name' => 'Test Division',
            'status' => 'active',
        ]);

        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'Original Financer Name',
            'company_number' => 'BE123456789',
            'status' => 'active',
        ]);

        $user = $this->createAuthUser();

        // Create some modules and attach them to the financer
        $module1 = Module::factory()->create(['is_core' => false]);
        $module2 = Module::factory()->create(['is_core' => false]);

        $division->modules()->attach([
            $module1->id => ['active' => true, 'price_per_beneficiary' => 1000],
            $module2->id => ['active' => true, 'price_per_beneficiary' => 2000],
        ]);

        $financer->modules()->attach([
            $module1->id => ['active' => true, 'price_per_beneficiary' => 1000],
            $module2->id => ['active' => true, 'price_per_beneficiary' => 2000],
        ]);

        $updateData = [
            'name' => 'Updated Financer Name',
            'company_number' => 'BE987654321',
            'division_id' => $division->id,
            'status' => 'active',
            'timezone' => 'Europe/Brussels',
            // Note: no 'modules' key
        ];

        // Act: Send PUT request without modules parameter
        $response = $this->actingAs($user)
            ->putJson("/api/v1/financers/{$financer->id}", $updateData);

        // Assert: Successful update
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'company_number',
                'status',
                'timezone',
                'division_id',
            ],
        ]);

        // Verify response contains updated data
        $this->assertEquals('Updated Financer Name', $response->json('data.name'));
        $this->assertEquals('BE987654321', $response->json('data.company_number'));
        $this->assertEquals('Europe/Brussels', $response->json('data.timezone'));

        // Verify modules remain unchanged
        $financer->refresh();
        $this->assertCount(2, $financer->modules);
        $this->assertTrue($financer->modules->contains($module1));
        $this->assertTrue($financer->modules->contains($module2));
    }

    #[Test]
    public function it_updates_financer_with_null_modules_parameter(): void
    {
        // Arrange
        $division = ModelFactory::createDivision([
            'name' => 'Test Division',
            'status' => 'active',
        ]);

        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'Original Name',
            'company_number' => 'BE111222333',
        ]);

        $user = $this->createAuthUser();

        // Attach some existing modules
        $module = Module::factory()->create(['is_core' => false]);
        $division->modules()->attach($module->id, ['active' => true, 'price_per_beneficiary' => 500]);
        $financer->modules()->attach($module->id, ['active' => true, 'price_per_beneficiary' => 500]);

        // Act: Update with null modules
        $response = $this->actingAs($user)
            ->putJson("/api/v1/financers/{$financer->id}", [
                'name' => 'Updated Name',
                'company_number' => 'BE444555666',
                'division_id' => $division->id,
                'modules' => null,  // Explicitly null
            ]);

        // Assert
        $response->assertOk();

        // Modules should remain unchanged
        $financer->refresh();
        $this->assertCount(1, $financer->modules);
        $this->assertTrue($financer->modules->contains($module));
    }

    #[Test]
    public function it_updates_financer_with_empty_modules_array(): void
    {
        // Arrange
        $division = ModelFactory::createDivision([
            'name' => 'Test Division',
            'status' => 'active',
        ]);

        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'Original Name',
            'company_number' => 'BE777888999',
        ]);

        $user = $this->createAuthUser();

        // Attach some existing modules
        $module = Module::factory()->create(['is_core' => false]);
        $division->modules()->attach($module->id, ['active' => true, 'price_per_beneficiary' => 750]);
        $financer->modules()->attach($module->id, ['active' => true, 'price_per_beneficiary' => 750]);

        // Act: Update with empty modules array
        $response = $this->actingAs($user)
            ->putJson("/api/v1/financers/{$financer->id}", [
                'name' => 'Updated Name',
                'company_number' => 'BE000111222',
                'division_id' => $division->id,
                'modules' => [],  // Empty array
            ]);

        // Assert
        $response->assertOk();

        // Modules should remain unchanged (empty array does not trigger processing)
        $financer->refresh();
        $this->assertCount(1, $financer->modules);
        $this->assertTrue($financer->modules->contains($module));
    }

    #[Test]
    public function it_maintains_existing_module_relationships_when_not_updated(): void
    {
        // Arrange
        $division = ModelFactory::createDivision([
            'name' => 'Test Division',
            'status' => 'active',
        ]);

        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'Original Name',
            'company_number' => 'BE333444555',
        ]);

        $user = $this->createAuthUser();

        // Create and attach multiple modules
        $coreModule = Module::factory()->create(['is_core' => true, 'name' => 'Core Module']);
        $module1 = Module::factory()->create(['is_core' => false, 'name' => 'Module 1']);
        $module2 = Module::factory()->create(['is_core' => false, 'name' => 'Module 2']);

        $division->modules()->attach([
            $coreModule->id => ['active' => true, 'price_per_beneficiary' => null],
            $module1->id => ['active' => true, 'price_per_beneficiary' => 1000],
            $module2->id => ['active' => true, 'price_per_beneficiary' => 2000],
        ]);

        $financer->modules()->attach([
            $coreModule->id => ['active' => true, 'price_per_beneficiary' => null, 'promoted' => true],
            $module1->id => ['active' => true, 'price_per_beneficiary' => 1000, 'promoted' => false],
            $module2->id => ['active' => false, 'price_per_beneficiary' => 2000, 'promoted' => true],
        ]);

        // Act: Update financer details without touching modules
        $response = $this->actingAs($user)
            ->putJson("/api/v1/financers/{$financer->id}", [
                'name' => 'Completely New Name',
                'company_number' => 'BE999000111',
                'division_id' => $division->id,
                'vat_number' => 'BE0123456789',
                'website' => 'https://example.com',
                // No modules parameter at all
            ]);

        // Assert
        $response->assertOk();

        // Verify financer details updated
        $financer->refresh();
        $this->assertEquals('Completely New Name', $financer->name);
        $this->assertEquals('BE999000111', $financer->company_number);
        $this->assertEquals('BE0123456789', $financer->vat_number);
        $this->assertEquals('https://example.com', $financer->website);

        // Verify all module relationships preserved exactly
        $this->assertCount(3, $financer->modules);

        $coreRelation = $financer->modules()->where('module_id', $coreModule->id)->first();
        $this->assertTrue($coreRelation->pivot->active);
        $this->assertNull($coreRelation->pivot->price_per_beneficiary);
        $this->assertTrue($coreRelation->pivot->promoted);

        $module1Relation = $financer->modules()->where('module_id', $module1->id)->first();
        $this->assertTrue($module1Relation->pivot->active);
        $this->assertEquals(1000, $module1Relation->pivot->price_per_beneficiary);
        $this->assertFalse($module1Relation->pivot->promoted);

        $module2Relation = $financer->modules()->where('module_id', $module2->id)->first();
        $this->assertFalse($module2Relation->pivot->active);
        $this->assertEquals(2000, $module2Relation->pivot->price_per_beneficiary);
        $this->assertTrue($module2Relation->pivot->promoted);
    }

    #[Test]
    public function it_processes_other_fields_when_modules_parameter_is_invalid_type(): void
    {
        // Arrange
        $division = ModelFactory::createDivision([
            'name' => 'Test Division',
            'status' => 'active',
        ]);

        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'Original Name',
            'company_number' => 'BE666777888',
        ]);

        $user = $this->createAuthUser();

        // Act: Send invalid modules type (should fail validation)
        $response = $this->actingAs($user)
            ->putJson("/api/v1/financers/{$financer->id}", [
                'name' => 'Should Not Update',
                'company_number' => 'BE111222333',
                'division_id' => $division->id,
                'modules' => 'not-an-array',  // Invalid type
            ]);

        // Assert: Validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['modules']);

        // Original data should remain unchanged
        $financer->refresh();
        $this->assertEquals('Original Name', $financer->name);
        $this->assertEquals('BE666777888', $financer->company_number);
    }
}
