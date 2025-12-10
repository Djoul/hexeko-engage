<?php

namespace Tests\Feature\Http\Controllers\V1;

use App\Enums\IDP\RoleDefaults;
use App\Models\ContractType;
use App\Models\Financer;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Traits\TestsFinancerSecurity;
use Tests\ProtectedRouteTestCase;

#[Group('contract_type')]
class ContractTypeControllerTest extends ProtectedRouteTestCase
{
    use TestsFinancerSecurity;

    protected bool $checkAuth = false;

    protected bool $checkPermissions = false;

    protected Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);
        $this->financer = $this->auth->financers->first();

        authorizationContext()->hydrateFromRequest($this->auth);
    }

    #[Test]
    public function it_can_list_contract_types(): void
    {
        // Arrange
        ContractType::factory()->count(3)->create(['financer_id' => $this->financer->id]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(route('contract-types.index', ['financer_id' => $this->financer->id]));

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'financer_id',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_filters_contract_types_by_name(): void
    {
        // Arrange
        ContractType::factory()->create([
            'name' => 'Permanent Contract',
            'financer_id' => $this->financer->id,
        ]);
        ContractType::factory()->create([
            'name' => 'Fixed-term Contract',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(route('contract-types.index', [
                'financer_id' => $this->financer->id,
                'name' => 'Permanent',
            ]));

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_filters_contract_types_by_financer_id(): void
    {
        // Arrange
        $otherFinancer = Financer::factory()->create();

        Context::add('accessible_financers', [$this->financer->id, $otherFinancer->id]);

        ContractType::factory()->create(['financer_id' => $this->financer->id]);
        ContractType::factory()->create(['financer_id' => $otherFinancer->id]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(route('contract-types.index', ['financer_id' => $this->financer->id]));

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_filters_contract_types_by_date_from(): void
    {
        // Arrange
        ContractType::factory()->create([
            'created_at' => '2023-01-01',
            'updated_at' => '2023-01-01',
            'financer_id' => $this->financer->id,
        ]);
        ContractType::factory()->create([
            'created_at' => '2024-01-02',
            'updated_at' => '2024-01-02',
            'financer_id' => $this->financer->id,
        ]);
        ContractType::factory()->create([
            'created_at' => '2024-01-03',
            'updated_at' => '2024-01-03',
            'financer_id' => $this->financer->id,
        ]);
        ContractType::factory()->create([
            'created_at' => '2024-01-04',
            'updated_at' => '2024-01-04',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('contract-types.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-01-02',
        ]));

        // Assert
        $response->assertOk()->assertJsonCount(3, 'data');

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('contract-types.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-01-03',
            'date_from_fields' => 'created_at',
        ]));

        // Assert
        $response->assertOk()->assertJsonCount(2, 'data');

        // Arrange
        ContractType::factory()->create([
            'created_at' => '2023-12-01',
            'updated_at' => '2024-03-01',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('contract-types.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-03-01',
            'date_from_fields' => ['created_at', 'updated_at'],
        ]));

        // Assert
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_filters_contract_types_by_date_to(): void
    {
        // Arrange
        ContractType::factory()->create(['updated_at' => '2023-01-31', 'financer_id' => $this->financer->id]);
        ContractType::factory()->create(['updated_at' => '2023-02-28', 'financer_id' => $this->financer->id]);
        ContractType::factory()->create(['updated_at' => '2023-02-01', 'financer_id' => $this->financer->id]);
        ContractType::factory()->create(['updated_at' => '2023-01-01', 'financer_id' => $this->financer->id]);

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('contract-types.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-01-31',
            'date_to_fields' => 'updated_at',
        ]));

        // Assert
        $response->assertOk()->assertJsonCount(2, 'data');

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('contract-types.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-02-28',
            'date_to_fields' => 'updated_at',
        ]));

        // Assert
        $response->assertOk()->assertJsonCount(4, 'data');

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('contract-types.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-02-01',
            'date_to_fields' => ['created_at', 'updated_at'],
        ]));

        // Assert
        $response->assertOk()->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_returns_no_contract_types_if_filters_do_not_match(): void
    {
        // Arrange
        // No contract types created

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('contract-types.index', [
            'financer_id' => $this->financer->id,
            'name' => 'Nonexistent Contract Type',
        ]));

        // Assert
        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_store_validates_input(): void
    {
        // Arrange
        // Empty payload

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson(route('contract-types.store', ['financer_id' => $this->financer->id]), []);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function it_persists_and_redirects_when_storing(): void
    {
        // Arrange
        $payload = [
            'name' => 'Permanent Contract',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson(route('contract-types.store', ['financer_id' => $this->financer->id]), $payload);

        // Assert
        $response->assertStatus(201);

        $createdContractTypeId = $response->json('data.id');
        $this->assertNotNull($createdContractTypeId);

        $this->assertDatabaseHas('contract_types', [
            'id' => $createdContractTypeId,
            'financer_id' => $this->financer->id,
        ]);

        $createdContractType = ContractType::find($createdContractTypeId);

        $this->assertEquals('Permanent Contract', $createdContractType->name);
    }

    #[Test]
    public function it_displays_a_contract_type(): void
    {
        // Arrange
        $contractType = ContractType::factory()->create(['financer_id' => $this->financer->id]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(route('contract-types.show', ['contract_type' => $contractType, 'financer_id' => $this->financer->id]));

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'financer_id',
                ],
            ]);
    }

    #[Test]
    public function it_validates_input_when_updating(): void
    {
        // Arrange
        $contractType = ContractType::factory()->create([
            'name' => 'Old Name',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson(route('contract-types.update', ['contract_type' => $contractType, 'financer_id' => $this->financer->id]), ['name' => '']);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseHas('contract_types', ['id' => $contractType->id]);

        $contractType->refresh();
        $this->assertEquals('Old Name', $contractType->name);
    }

    #[Test]
    public function it_persists_changes_and_redirects_when_updating(): void
    {
        // Arrange
        $name = 'Old Contract Type';

        $contractType = ContractType::factory()->create([
            'name' => $name,
            'financer_id' => $this->financer->id,
        ]);

        $newName = 'New Contract Type';

        $payload = [
            'name' => $newName,
            'financer_id' => $this->financer->id,
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson(route('contract-types.update', ['contract_type' => $contractType, 'financer_id' => $this->financer->id]), $payload);

        // Assert
        $response->assertOk();

        $this->assertDatabaseHas('contract_types', [
            'id' => $contractType->id,
        ]);

        $updatedContractType = $contractType->fresh();

        $this->assertEquals('New Contract Type', $updatedContractType->name);
    }

    #[Test]
    public function it_deletes_and_redirects(): void
    {
        // Arrange
        $contractType = ContractType::factory()->create(['financer_id' => $this->financer->id]);

        // Act
        $response = $this->actingAs($this->auth)
            ->deleteJson(route('contract-types.destroy', ['contract_type' => $contractType, 'financer_id' => $this->financer->id]));

        // Assert
        $response->assertStatus(204);
        $this->assertSoftDeleted('contract_types', ['id' => $contractType->id]);
    }

    #[Test]
    public function it_can_paginate_contract_types(): void
    {
        // Arrange
        ContractType::factory()->count(25)->create(['financer_id' => $this->financer->id]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(route('contract-types.index', [
                'financer_id' => $this->financer->id,
                'per_page' => 10,
            ]));

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);
    }

    #[Test]
    public function it_enforces_financer_security(): void
    {
        $this->assertCannotAccessOtherFinancerResource(ContractType::class, 'contract-types', 'contract_type');
    }
}
