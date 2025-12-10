<?php

namespace Tests\Feature\Http\Controllers\V1\DivisionController;

use App\Enums\Countries;
use App\Enums\Currencies;
use App\Enums\Languages;
use App\Enums\TimeZones;
use DB;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['divisions', 'module'], scope: 'test')]
#[Group('division')]
class UpdateDivisionTest extends ProtectedRouteTestCase
{
    use WithFaker;

    const URI = '/api/v1/divisions/';

    protected function setUp(): void
    {
        parent::setUp();

        // Create a core module for pricing history tracking
        DB::table('modules')->insert([
            'id' => '01946c4e-7001-7000-8000-000000000001',
            'name' => json_encode(['en' => 'Core Module', 'fr' => 'Module Core']),
            'description' => json_encode(['en' => 'Core functionality', 'fr' => 'Fonctionnalité principale']),
            'category' => 'enterprise_life',
            'is_core' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function it_can_update_division(): void
    {
        $division = ModelFactory::createDivision(['name' => 'Division Test']);

        $updatedData = [
            'name' => 'Division Test Updated',
            'remarks' => $this->faker->sentence,
            'country' => $this->faker->randomElement(Countries::getValues()),
            'currency' => $this->faker->randomElement(Currencies::getValues()),
            'timezone' => $this->faker->randomElement(TimeZones::getValues()),
            'language' => $this->faker->randomElement(Languages::getValues()),
            'created_at' => $division->created_at,
            'updated_at' => $division->updated_at,
        ];

        $this->assertDatabaseCount('divisions', 1);
        $response = $this->putJson(self::URI."{$division->id}", $updatedData);

        $response->assertStatus(200);

        $this->assertDatabaseCount('divisions', 1);
        $this->assertDatabaseHas('divisions', ['id' => $division['id'], 'name' => $updatedData['name']]);

    }

    #[Test]
    public function it_can_update_division_with_core_package_price(): void
    {
        $division = ModelFactory::createDivision(['name' => 'Division Test', 'core_package_price' => null]);

        $updatedData = [
            'name' => 'Division Test Updated',
            'remarks' => $this->faker->sentence,
            'country' => $this->faker->randomElement(Countries::getValues()),
            'currency' => $this->faker->randomElement(Currencies::getValues()),
            'timezone' => $this->faker->randomElement(TimeZones::getValues()),
            'language' => $this->faker->randomElement(Languages::getValues()),
            'core_package_price' => 5000, // 50 euros in cents
        ];

        $this->assertDatabaseCount('divisions', 1);
        $response = $this->putJson(self::URI."{$division->id}", $updatedData);

        $response->assertStatus(200);

        $this->assertDatabaseCount('divisions', 1);
        $this->assertDatabaseHas('divisions', [
            'id' => $division['id'],
            'name' => $updatedData['name'],
            'core_package_price' => 5000,
        ]);

        // Verify pricing history was recorded
        $this->assertDatabaseHas('module_pricing_history', [
            'entity_id' => $division->id,
            'entity_type' => 'division',
            'old_price' => null,
            'new_price' => 5000,
            'price_type' => 'core_package',
        ]);
    }

    #[Test]
    public function it_can_update_existing_core_package_price(): void
    {
        $division = ModelFactory::createDivision(['name' => 'Division Test', 'core_package_price' => 3000]);

        $updatedData = [
            'name' => 'Division Test',
            'country' => $division->country,
            'currency' => $division->currency,
            'timezone' => $division->timezone,
            'language' => $division->language,
            'core_package_price' => 4500, // Update from 30 to 45 euros
        ];

        $response = $this->putJson(self::URI."{$division->id}", $updatedData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('divisions', [
            'id' => $division['id'],
            'core_package_price' => 4500,
        ]);

        // Verify pricing history was recorded
        $this->assertDatabaseHas('module_pricing_history', [
            'entity_id' => $division->id,
            'entity_type' => 'division',
            'old_price' => 3000,
            'new_price' => 4500,
            'price_type' => 'core_package',
        ]);
    }
}
