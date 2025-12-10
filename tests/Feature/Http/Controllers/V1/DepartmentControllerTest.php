<?php

namespace Tests\Feature\Http\Controllers\V1;

use App\Enums\IDP\RoleDefaults;
use App\Models\Department;
use App\Models\Financer;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Traits\TestsFinancerSecurity;
use Tests\ProtectedRouteTestCase;

#[Group('department')]
class DepartmentControllerTest extends ProtectedRouteTestCase
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
    public function it_can_list_departments(): void
    {
        Department::factory()->count(3)->create(['financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('departments.index', ['financer_id' => $this->financer->id]));

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
    public function it_filters_departments_by_name(): void
    {
        Department::factory()->create([
            'name' => 'Engineering Department',
            'financer_id' => $this->financer->id,
        ]);
        Department::factory()->create([
            'name' => 'Sales Department',
            'financer_id' => $this->financer->id,
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('departments.index', [
                'financer_id' => $this->financer->id,
                'name' => 'Engineering',
            ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_filters_departments_by_financer_id(): void
    {
        $otherFinancer = Financer::factory()->create();

        Context::add('accessible_financers', [$this->financer->id, $otherFinancer->id]);

        Department::factory()->create(['financer_id' => $this->financer->id]);
        Department::factory()->create(['financer_id' => $otherFinancer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('departments.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_filters_departments_by_date_from(): void
    {
        Department::factory()->create([
            'created_at' => '2023-01-01',
            'updated_at' => '2023-01-01',
            'financer_id' => $this->financer->id,
        ]);
        Department::factory()->create([
            'created_at' => '2024-01-02',
            'updated_at' => '2024-01-02',
            'financer_id' => $this->financer->id,
        ]);
        Department::factory()->create([
            'created_at' => '2024-01-03',
            'updated_at' => '2024-01-03',
            'financer_id' => $this->financer->id,
        ]);
        Department::factory()->create([
            'created_at' => '2024-01-04',
            'updated_at' => '2024-01-04',
            'financer_id' => $this->financer->id,
        ]);

        // Filter by created_at field (default)
        $response = $this->actingAs($this->auth)->getJson(route('departments.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-01-02',
        ]));
        $response->assertOk()->assertJsonCount(3, 'data');

        // Filter by created_at field with explicit field specification
        $response = $this->actingAs($this->auth)->getJson(route('departments.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-01-03',
            'date_from_fields' => 'created_at',
        ]));
        $response->assertOk()->assertJsonCount(2, 'data');

        // Filter by multiple date fields
        Department::factory()->create([
            'created_at' => '2023-12-01',
            'updated_at' => '2024-03-01',
            'financer_id' => $this->financer->id,
        ]);
        $response = $this->actingAs($this->auth)->getJson(route('departments.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-03-01',
            'date_from_fields' => ['created_at', 'updated_at'],
        ]));
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_filters_departments_by_date_to(): void
    {
        Department::factory()->create(['updated_at' => '2023-01-31', 'financer_id' => $this->financer->id]);
        Department::factory()->create(['updated_at' => '2023-02-28', 'financer_id' => $this->financer->id]);
        Department::factory()->create(['updated_at' => '2023-02-01', 'financer_id' => $this->financer->id]);
        Department::factory()->create(['updated_at' => '2023-01-01', 'financer_id' => $this->financer->id]);

        // Filter by updated_at field specifically
        $response = $this->actingAs($this->auth)->getJson(route('departments.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-01-31',
            'date_to_fields' => 'updated_at',
        ]));
        $response->assertOk()->assertJsonCount(2, 'data');

        $response = $this->actingAs($this->auth)->getJson(route('departments.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-02-28',
            'date_to_fields' => 'updated_at',
        ]));
        $response->assertOk()->assertJsonCount(4, 'data');

        $response = $this->actingAs($this->auth)->getJson(route('departments.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-02-01',
            'date_to_fields' => ['created_at', 'updated_at'],
        ]));
        $response->assertOk()->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_returns_no_departments_if_filters_do_not_match(): void
    {
        $response = $this->actingAs($this->auth)->getJson(route('departments.index', [
            'financer_id' => $this->financer->id,
            'name' => 'Nonexistent Department',
        ]));

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_store_validates_input(): void
    {
        $this->actingAs($this->auth)
            ->postJson(route('departments.store', ['financer_id' => $this->financer->id]), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function it_persists_and_redirects_when_storing(): void
    {
        $payload = [
            'name' => 'Marketing Department',
            'financer_id' => $this->financer->id,
        ];

        $response = $this->actingAs($this->auth)
            ->postJson(route('departments.store', ['financer_id' => $this->financer->id]), $payload);

        $response->assertStatus(201);

        // Récupérer l'ID du département créé depuis la réponse
        $createdDepartmentId = $response->json('data.id');
        $this->assertNotNull($createdDepartmentId);

        // Vérifier que le département a été créé avec les bonnes données
        $this->assertDatabaseHas('departments', [
            'id' => $createdDepartmentId,
            'financer_id' => $this->financer->id,
        ]);

        // Vérifier le nom
        $createdDepartment = Department::find($createdDepartmentId);
        $this->assertEquals('Marketing Department', $createdDepartment->name);
    }

    #[Test]
    public function it_displays_a_department(): void
    {
        $department = Department::factory()->create(['financer_id' => $this->financer->id]);

        $this->actingAs($this->auth)
            ->getJson(route('departments.show', ['department' => $department, 'financer_id' => $this->financer->id]))
            ->assertOk()
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
        $department = Department::factory()->create([
            'name' => 'Old Name',
            'financer_id' => $this->financer->id,
        ]);

        $this->actingAs($this->auth)
            ->putJson(route('departments.update', ['department' => $department, 'financer_id' => $this->financer->id]), ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseHas('departments', ['id' => $department->id]);

        $department->refresh();
        $this->assertEquals('Old Name', $department->name);
    }

    #[Test]
    public function it_persists_changes_and_redirects_when_updating(): void
    {
        $department = Department::factory()->create([
            'name' => 'Old Department',
            'financer_id' => $this->financer->id,
        ]);

        $payload = [
            'name' => 'New Department',
            'financer_id' => $this->financer->id,
        ];

        $response = $this->actingAs($this->auth)
            ->putJson(route('departments.update', ['department' => $department, 'financer_id' => $this->financer->id]), $payload);

        $response->assertOk();

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
        ]);

        // Rafraîchir le département depuis la base de données
        $updatedDepartment = $department->fresh();
        $this->assertEquals('New Department', $updatedDepartment->name);
    }

    #[Test]
    public function it_deletes_and_redirects(): void
    {
        $department = Department::factory()->create(['financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->deleteJson(route('departments.destroy', ['department' => $department, 'financer_id' => $this->financer->id]));

        $response->assertStatus(204);
        $this->assertSoftDeleted('departments', ['id' => $department->id]);
    }

    #[Test]
    public function it_can_paginate_departments(): void
    {
        Department::factory()->count(25)->create(['financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('departments.index', [
                'financer_id' => $this->financer->id,
                'per_page' => 10,
            ]));

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
        $this->assertCannotAccessOtherFinancerResource(Department::class, 'departments', 'department');
    }
}
