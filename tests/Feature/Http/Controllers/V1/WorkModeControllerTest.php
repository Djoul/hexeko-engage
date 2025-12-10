<?php

namespace Tests\Feature\Http\Controllers\V1;

use App\Enums\IDP\RoleDefaults;
use App\Models\Financer;
use App\Models\WorkMode;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Traits\TestsFinancerSecurity;
use Tests\ProtectedRouteTestCase;

#[Group('workmode')]
class WorkModeControllerTest extends ProtectedRouteTestCase
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
    public function it_can_list_work_modes(): void
    {
        // Arrange
        WorkMode::factory()->count(3)->create(['financer_id' => $this->financer->id]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(route('work-modes.index', ['financer_id' => $this->financer->id]));

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
    public function it_filters_work_modes_by_name(): void
    {
        // Arrange
        WorkMode::factory()->create([
            'name' => 'Paris Office',
            'financer_id' => $this->financer->id,
        ]);
        WorkMode::factory()->create([
            'name' => 'London Office',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(route('work-modes.index', [
                'financer_id' => $this->financer->id,
                'name' => 'Paris',
            ]));

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_filters_work_modes_by_financer_id(): void
    {
        // Arrange
        $otherFinancer = Financer::factory()->create();

        Context::add('accessible_financers', [$this->financer->id, $otherFinancer->id]);

        WorkMode::factory()->create(['financer_id' => $this->financer->id]);
        WorkMode::factory()->create(['financer_id' => $otherFinancer->id]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(route('work-modes.index', ['financer_id' => $this->financer->id]));

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_filters_work_modes_by_date_from(): void
    {
        // Arrange
        WorkMode::factory()->create([
            'created_at' => '2023-01-01',
            'updated_at' => '2023-01-01',
            'financer_id' => $this->financer->id,
        ]);
        WorkMode::factory()->create([
            'created_at' => '2024-01-02',
            'updated_at' => '2024-01-02',
            'financer_id' => $this->financer->id,
        ]);
        WorkMode::factory()->create([
            'created_at' => '2024-01-03',
            'updated_at' => '2024-01-03',
            'financer_id' => $this->financer->id,
        ]);
        WorkMode::factory()->create([
            'created_at' => '2024-01-04',
            'updated_at' => '2024-01-04',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('work-modes.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-01-02',
        ]));

        // Assert
        $response->assertOk()->assertJsonCount(3, 'data');

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('work-modes.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-01-03',
            'date_from_fields' => 'created_at',
        ]));

        // Assert
        $response->assertOk()->assertJsonCount(2, 'data');

        // Arrange
        WorkMode::factory()->create([
            'created_at' => '2023-12-01',
            'updated_at' => '2024-03-01',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('work-modes.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-03-01',
            'date_from_fields' => ['created_at', 'updated_at'],
        ]));

        // Assert
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_filters_work_modes_by_date_to(): void
    {
        // Arrange
        WorkMode::factory()->create(['updated_at' => '2023-01-31', 'financer_id' => $this->financer->id]);
        WorkMode::factory()->create(['updated_at' => '2023-02-28', 'financer_id' => $this->financer->id]);
        WorkMode::factory()->create(['updated_at' => '2023-02-01', 'financer_id' => $this->financer->id]);
        WorkMode::factory()->create(['updated_at' => '2023-01-01', 'financer_id' => $this->financer->id]);

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('work-modes.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-01-31',
            'date_to_fields' => 'updated_at',
        ]));

        // Assert
        $response->assertOk()->assertJsonCount(2, 'data');

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('work-modes.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-02-28',
            'date_to_fields' => 'updated_at',
        ]));

        // Assert
        $response->assertOk()->assertJsonCount(4, 'data');

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('work-modes.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-02-01',
            'date_to_fields' => ['created_at', 'updated_at'],
        ]));

        // Assert
        $response->assertOk()->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_returns_no_work_modes_if_filters_do_not_match(): void
    {
        // Arrange
        // No work modes created

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('work-modes.index', [
            'financer_id' => $this->financer->id,
            'name' => 'Nonexistent WorkMode',
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
            ->postJson(route('work-modes.store', ['financer_id' => $this->financer->id]), []);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function it_persists_and_redirects_when_storing(): void
    {
        // Arrange
        $payload = [
            'name' => 'Test Work Mode',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson(route('work-modes.store', ['financer_id' => $this->financer->id]), $payload);

        // Assert
        $response->assertStatus(201);

        $createdWorkModeId = $response->json('data.id');
        $this->assertNotNull($createdWorkModeId);

        $this->assertDatabaseHas('work_modes', [
            'id' => $createdWorkModeId,
            'financer_id' => $this->financer->id,
        ]);

        $createdWorkMode = WorkMode::find($createdWorkModeId);

        $this->assertEquals('Test Work Mode', $createdWorkMode->name);
    }

    #[Test]
    public function it_displays_a_work_mode(): void
    {
        // Arrange
        $workMode = WorkMode::factory()->create(['financer_id' => $this->financer->id]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(route('work-modes.show', ['work_mode' => $workMode, 'financer_id' => $this->financer->id]));

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
        $workMode = WorkMode::factory()->create([
            'name' => 'Old Name',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson(route('work-modes.update', ['work_mode' => $workMode, 'financer_id' => $this->financer->id]), ['name' => '']);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseHas('work_modes', ['id' => $workMode->id]);

        $workMode->refresh();
        $this->assertEquals('Old Name', $workMode->name);
    }

    #[Test]
    public function it_persists_changes_and_redirects_when_updating(): void
    {
        // Arrange
        $workMode = WorkMode::factory()->create([
            'name' => 'Old WorkMode',
            'financer_id' => $this->financer->id,
        ]);

        $payload = [
            'name' => 'New WorkMode',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson(route('work-modes.update', ['work_mode' => $workMode, 'financer_id' => $this->financer->id]), $payload);

        // Assert
        $response->assertOk();

        $this->assertDatabaseHas('work_modes', [
            'id' => $workMode->id,
        ]);

        $updatedWorkMode = $workMode->fresh();

        $this->assertEquals('New WorkMode', $updatedWorkMode->name);
    }

    #[Test]
    public function it_deletes_and_redirects(): void
    {
        // Arrange
        $workMode = WorkMode::factory()->create(['financer_id' => $this->financer->id]);

        // Act
        $response = $this->actingAs($this->auth)
            ->deleteJson(route('work-modes.destroy', ['work_mode' => $workMode, 'financer_id' => $this->financer->id]));

        // Assert
        $response->assertStatus(204);
        $this->assertSoftDeleted('work_modes', ['id' => $workMode->id]);
    }

    #[Test]
    public function it_can_paginate_work_modes(): void
    {
        // Arrange
        WorkMode::factory()->count(25)->create(['financer_id' => $this->financer->id]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(route('work-modes.index', [
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
        $this->assertCannotAccessOtherFinancerResource(WorkMode::class, 'work-modes', 'work_mode');
    }
}
