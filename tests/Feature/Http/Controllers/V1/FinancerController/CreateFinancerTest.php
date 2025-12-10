<?php

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use App\Models\Division;
use App\Models\Financer;
use App\Models\Module;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['financers'], scope: 'test')]
#[Group('financer')]
class CreateFinancerTest extends ProtectedRouteTestCase
{
    use WithFaker;

    protected $createFinancerAction;

    protected function setUp(): void
    {
        parent::setUp();

    }

    #[Test]
    public function it_can_create_financer(): void
    {
        $this->assertDatabaseCount('financers', 0);

        $financerData = ModelFactory::makeFinancer(['name' => 'Financer Test'])->toArray();
        $financerData['company_number'] = 'TEST123456';

        $response = $this->post('/api/v1/financers', $financerData);

        $response->assertStatus(201);

        $this->assertDatabaseCount('financers', 1);

        $this->assertDatabaseHas('financers', ['name' => $financerData['name']]);
    }

    #[Test]
    public function it_can_create_financer_with_new_fields(): void
    {
        $this->assertDatabaseCount('financers', 0);

        $financerData = ModelFactory::makeFinancer([
            'name' => 'Financer Test',
        ])->toArray();

        $financerData['status'] = 'pending';
        $financerData['bic'] = 'BNPAFRPPXXX';
        $financerData['company_number'] = 'BE0123456789';

        $response = $this->post('/api/v1/financers', $financerData);

        $response->assertStatus(201);

        $this->assertDatabaseCount('financers', 1);

        $this->assertDatabaseHas('financers', [
            'name' => $financerData['name'],
            'status' => 'pending',
            'bic' => 'BNPAFRPPXXX',
            'company_number' => 'BE0123456789',
        ]);

        // Verify response includes new fields
        $response->assertJsonFragment([
            'status' => 'pending',
            'bic' => 'BNPAFRPPXXX',
            'company_number' => 'BE0123456789',
        ]);
    }

    #[Test]
    public function it_does_not_automatically_activate_division_modules_on_creation(): void
    {
        // Arrange
        $division = Division::factory()->create([
            'name' => 'Test Division',
            'core_package_price' => 10000, // 100 EUR
        ]);

        // Create modules for the division
        $module1 = Module::factory()->create(['name' => ['en' => 'Module 1']]);
        $module2 = Module::factory()->create(['name' => ['en' => 'Module 2']]);

        // Attach modules to division with prices
        $division->modules()->attach($module1->id, [
            'active' => true,
            'price_per_beneficiary' => 100, // 1 EUR
        ]);
        $division->modules()->attach($module2->id, [
            'active' => true,
            'price_per_beneficiary' => 200, // 2 EUR
        ]);

        $financerData = ModelFactory::makeFinancer([
            'name' => 'Test Financer',
            'division_id' => $division->id,
        ])->toArray();
        $financerData['company_number'] = 'TEST123456';

        // Act
        $response = $this->post('/api/v1/financers', $financerData);

        // Assert
        $response->assertStatus(201);

        $financer = Financer::where('name', 'Test Financer')->first();
        $this->assertNotNull($financer);

        // Verify core_package_price is NOT automatically calculated
        // It should only be set if explicitly provided in request
        if (! isset($financerData['core_package_price'])) {
            $this->assertNull($financer->core_package_price, 'Core package price should not be automatically calculated');
        }

        // Verify NO modules are automatically attached
        $financerModules = $financer->modules()->get();
        $this->assertCount(0, $financerModules, 'No modules should be automatically attached');
    }

    #[Test]
    public function it_handles_division_without_modules(): void
    {
        // Arrange
        $division = Division::factory()->create([
            'name' => 'Empty Division',
            'core_package_price' => 5000,
        ]);

        $financerData = ModelFactory::makeFinancer([
            'name' => 'Test Financer',
            'division_id' => $division->id,
        ])->toArray();
        $financerData['company_number'] = 'TEST123456';

        // Act
        $response = $this->post('/api/v1/financers', $financerData);

        // Assert
        $response->assertStatus(201);

        $financer = Financer::where('name', 'Test Financer')->first();
        $this->assertNotNull($financer);
        $this->assertCount(0, $financer->modules, 'Financer should have no modules');

        // Verify core_package_price is NOT automatically calculated
        if (! isset($financerData['core_package_price'])) {
            $this->assertNull($financer->core_package_price, 'Core price should not be automatically calculated');
        }
    }

    #[Test]
    public function it_handles_division_with_null_core_package_price(): void
    {
        // Arrange
        $division = Division::factory()->create([
            'name' => 'Division Without Core Price',
            'core_package_price' => null,
        ]);

        $module = Module::factory()->create(['name' => ['en' => 'Module']]);
        $division->modules()->attach($module->id, [
            'active' => true,
            'price_per_beneficiary' => 150,
        ]);

        $financerData = ModelFactory::makeFinancer([
            'name' => 'Test Financer',
            'division_id' => $division->id,
        ])->toArray();
        $financerData['company_number'] = 'TEST123456';

        // Act
        $response = $this->post('/api/v1/financers', $financerData);

        // Assert
        $response->assertStatus(201);

        $financer = Financer::where('name', 'Test Financer')->first();
        $this->assertNotNull($financer);
        $this->assertNull($financer->core_package_price, 'Core price should remain null');

        // Verify NO modules are automatically attached
        $this->assertCount(0, $financer->modules, 'No modules should be automatically attached');
    }

    #[Test]
    public function it_can_create_financer_without_automatic_module_activation(): void
    {
        // Arrange
        $division = Division::factory()->create([
            'name' => 'Test Division',
            'core_package_price' => 10000,
        ]);

        $module = Module::factory()->create(['name' => ['en' => 'Module Without Price']]);
        $division->modules()->attach($module->id, [
            'active' => true,
            'price_per_beneficiary' => null,
        ]);

        $financerData = ModelFactory::makeFinancer([
            'name' => 'Test Financer',
            'division_id' => $division->id,
        ])->toArray();
        $financerData['company_number'] = 'TEST123456';

        // Act
        $response = $this->post('/api/v1/financers', $financerData);

        // Assert
        $response->assertStatus(201);

        $financer = Financer::where('name', 'Test Financer')->first();
        $this->assertNotNull($financer);

        // Verify NO modules are automatically attached
        $this->assertCount(0, $financer->modules, 'No modules should be automatically attached');
    }
}
