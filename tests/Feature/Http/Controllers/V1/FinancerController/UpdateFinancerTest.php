<?php

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use DB;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\Helpers\Traits\GeneratesUniqueModuleIds;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['financers', 'modules'], scope: 'test')]
#[Group('financer')]
class UpdateFinancerTest extends ProtectedRouteTestCase
{
    use GeneratesUniqueModuleIds, WithFaker;

    const URI = '/api/v1/financers/';

    protected function setUp(): void
    {
        parent::setUp();

        // Create a core module for pricing history tracking
        DB::table('modules')->insert([
            'id' => $this->getCoreModuleId(),
            'name' => json_encode(['en' => 'Core Module', 'fr' => 'Module Core']),
            'description' => json_encode(['en' => 'Core functionality', 'fr' => 'Fonctionnalité principale']),
            'category' => 'enterprise_life',
            'is_core' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function it_can_update_financer(): void
    {
        $financer = ModelFactory::createFinancer(['name' => 'Financer Test']);

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
            'created_at' => $financer->created_at,
            'updated_at' => $financer->updated_at,
        ];

        $this->assertDatabaseCount('financers', 1);
        $response = $this->put(self::URI."{$financer->id}", $updatedData);

        $response->assertStatus(200);

        $this->assertDatabaseCount('financers', 1);
        $this->assertDatabaseHas('financers', ['id' => $financer['id'], 'name' => $updatedData['name']]);

    }

    #[Test]
    public function it_can_update_financer_with_new_fields(): void
    {
        $financer = ModelFactory::createFinancer(['name' => 'Financer Test']);

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
            'status' => 'active',
            'bic' => 'BNPAFRPP',
            'company_number' => 'BE9876543210',
            'created_at' => $financer->created_at,
            'updated_at' => $financer->updated_at,
        ];

        $this->assertDatabaseCount('financers', 1);
        $response = $this->put(self::URI."{$financer->id}", $updatedData);

        $response->assertStatus(200);

        $this->assertDatabaseCount('financers', 1);
        $this->assertDatabaseHas('financers', [
            'id' => $financer['id'],
            'name' => $updatedData['name'],
            'status' => 'active',
            'bic' => 'BNPAFRPP',
            'company_number' => 'BE9876543210',
        ]);

        // Verify response includes new fields
        $response->assertJsonFragment([
            'status' => 'active',
            'bic' => 'BNPAFRPP',
            'company_number' => 'BE9876543210',
        ]);
    }

    #[Test]
    public function it_can_update_financer_with_core_package_price(): void
    {
        $division = ModelFactory::createDivision(['name' => 'Division Test']);
        $financer = ModelFactory::createFinancer([
            'name' => 'Financer Test',
            'division_id' => $division->id,
            'core_package_price' => null,
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
            'core_package_price' => 4500, // 45 euros in cents
        ];

        $this->assertDatabaseCount('financers', 1);
        $response = $this->put(self::URI."{$financer->id}", $updatedData);

        $response->assertStatus(200);

        $this->assertDatabaseCount('financers', 1);
        $this->assertDatabaseHas('financers', [
            'id' => $financer['id'],
            'name' => $updatedData['name'],
            'core_package_price' => 4500,
        ]);

        // Verify pricing history was recorded
        $this->assertDatabaseHas('module_pricing_history', [
            'entity_id' => $financer->id,
            'entity_type' => 'financer',
            'old_price' => null,
            'new_price' => 4500,
            'price_type' => 'core_package',
        ]);
    }

    #[Test]
    public function it_can_update_existing_financer_core_package_price(): void
    {
        $division = ModelFactory::createDivision(['name' => 'Division Test']);
        $financer = ModelFactory::createFinancer([
            'name' => 'Financer Test',
            'division_id' => $division->id,
            'core_package_price' => 3500,
        ]);

        $updatedData = [
            'name' => 'Financer Test',
            'timezone' => $financer->timezone,
            'division_id' => $financer->division_id,
            'company_number' => $financer->company_number,
            'core_package_price' => 5500, // Update from 35 to 55 euros
        ];

        $response = $this->put(self::URI."{$financer->id}", $updatedData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('financers', [
            'id' => $financer['id'],
            'core_package_price' => 5500,
        ]);

        // Verify pricing history was recorded
        $this->assertDatabaseHas('module_pricing_history', [
            'entity_id' => $financer->id,
            'entity_type' => 'financer',
            'old_price' => 3500,
            'new_price' => 5500,
            'price_type' => 'core_package',
        ]);
    }
}
