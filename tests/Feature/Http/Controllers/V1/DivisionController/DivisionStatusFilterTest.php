<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\DivisionController;

use App\Enums\DivisionStatus;
use App\Models\Division;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['divisions'], scope: 'test')]
#[Group('division')]
#[Group('filters')]
class DivisionStatusFilterTest extends ProtectedRouteTestCase
{
    private const URI = '/api/v1/divisions';

    protected function setUp(): void
    {
        parent::setUp();

    }

    #[Test]
    public function it_filters_divisions_by_single_status(): void
    {
        // Arrange
        Division::factory()->create(['status' => DivisionStatus::ACTIVE, 'name' => 'Active Division']);
        Division::factory()->create(['status' => DivisionStatus::PENDING, 'name' => 'Pending Division']);
        Division::factory()->create(['status' => DivisionStatus::ARCHIVED, 'name' => 'Archived Division']);

        // Act
        $response = $this->get(self::URI.'?status=active');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', DivisionStatus::ACTIVE);
        $response->assertJsonPath('data.0.name', 'Active Division');
    }

    #[Test]
    public function it_filters_divisions_by_multiple_statuses(): void
    {
        // Arrange
        Division::factory()->create(['status' => DivisionStatus::ACTIVE, 'name' => 'Active Division']);
        Division::factory()->create(['status' => DivisionStatus::PENDING, 'name' => 'Pending Division']);
        Division::factory()->create(['status' => DivisionStatus::ARCHIVED, 'name' => 'Archived Division']);

        // Act
        $response = $this->get(self::URI.'?status=active,pending');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');

        $statuses = collect($response->json('data'))->pluck('status')->toArray();
        $this->assertContains(DivisionStatus::ACTIVE, $statuses);
        $this->assertContains(DivisionStatus::PENDING, $statuses);
        $this->assertNotContains(DivisionStatus::ARCHIVED, $statuses);
    }

    #[Test]
    public function it_returns_all_divisions_when_no_status_filter_is_provided(): void
    {
        // Arrange
        Division::factory()->create(['status' => DivisionStatus::ACTIVE]);
        Division::factory()->create(['status' => DivisionStatus::PENDING]);
        Division::factory()->create(['status' => DivisionStatus::ARCHIVED]);

        // Act
        $response = $this->get(self::URI);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_ignores_invalid_status_values(): void
    {
        // Arrange
        Division::factory()->create(['status' => DivisionStatus::ACTIVE, 'name' => 'Active Division']);
        Division::factory()->create(['status' => DivisionStatus::PENDING, 'name' => 'Pending Division']);

        // Act
        $response = $this->get(self::URI.'?status=active,invalid_status');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', DivisionStatus::ACTIVE);
    }

    #[Test]
    public function it_handles_empty_status_parameter(): void
    {
        // Arrange
        Division::factory()->create(['status' => DivisionStatus::ACTIVE]);
        Division::factory()->create(['status' => DivisionStatus::PENDING]);

        // Act
        $response = $this->get(self::URI.'?status=');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_combines_status_filter_with_other_filters(): void
    {
        // Arrange
        Division::factory()->create([
            'status' => DivisionStatus::ACTIVE,
            'name' => 'Active Tech Division',
        ]);
        Division::factory()->create([
            'status' => DivisionStatus::ACTIVE,
            'name' => 'Active Sales Division',
        ]);
        Division::factory()->create([
            'status' => DivisionStatus::PENDING,
            'name' => 'Pending Tech Division',
        ]);

        // Act
        $response = $this->get(self::URI.'?status=active&search=Tech');

        // Assert
        $response->assertStatus(200);

        // Debug: Check what we actually get
        $data = $response->json('data');
        $names = collect($data)->pluck('name')->toArray();

        // The search should find only "Active Tech Division" (status=active AND name contains "Tech")
        $this->assertCount(1, $data, 'Expected 1 result, but got: '.json_encode($names));
        $this->assertEquals('Active Tech Division', $data[0]['name']);
        $this->assertEquals(DivisionStatus::ACTIVE, $data[0]['status']);
    }

    #[Test]
    public function it_filters_all_status_values(): void
    {
        // Arrange
        Division::factory()->create(['status' => DivisionStatus::ACTIVE, 'name' => 'Active Division']);
        Division::factory()->create(['status' => DivisionStatus::PENDING, 'name' => 'Pending Division']);
        Division::factory()->create(['status' => DivisionStatus::ARCHIVED, 'name' => 'Archived Division']);

        // Act
        $response = $this->get(self::URI.'?status=active,pending,archived');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Active Division', $names);
        $this->assertContains('Pending Division', $names);
        $this->assertContains('Archived Division', $names);
    }

    #[Test]
    public function it_handles_status_with_spaces(): void
    {
        // Arrange
        Division::factory()->create(['status' => DivisionStatus::ACTIVE]);
        Division::factory()->create(['status' => DivisionStatus::PENDING]);
        Division::factory()->create(['status' => DivisionStatus::ARCHIVED]);

        // Act
        $response = $this->get(self::URI.'?status='.urlencode(' active , pending '));

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');

        $statuses = collect($response->json('data'))->pluck('status')->toArray();
        $this->assertContains(DivisionStatus::ACTIVE, $statuses);
        $this->assertContains(DivisionStatus::PENDING, $statuses);
    }

    #[Test]
    public function it_works_with_status_filter(): void
    {
        // Arrange
        Division::factory()->count(15)->create(['status' => DivisionStatus::ACTIVE]);
        Division::factory()->count(5)->create(['status' => DivisionStatus::PENDING]);

        // Act
        $response = $this->get(self::URI.'?status=active');

        // Assert
        $response->assertStatus(200);
        // Since pagination is not implemented, it should return all 15 active divisions
        $response->assertJsonCount(15, 'data');

        // Verify all returned items are active
        $statuses = collect($response->json('data'))->pluck('status')->unique()->toArray();
        $this->assertEquals([DivisionStatus::ACTIVE], $statuses);
    }

    #[Test]
    public function it_handles_only_invalid_status_values(): void
    {
        // Arrange - This reproduces Sentry issue ENGAGE-MAIN-API-9P
        Division::factory()->create(['status' => DivisionStatus::ACTIVE]);
        Division::factory()->create(['status' => DivisionStatus::PENDING]);

        // Act - Send only invalid status (all values will be filtered out)
        $response = $this->get(self::URI.'?status=invalid_status');

        // Assert - Should return all divisions without crashing
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_handles_invalid_first_status_with_valid_second(): void
    {
        // Arrange - Reproduces exact Sentry bug: status=inactive,archived
        Division::factory()->create(['status' => DivisionStatus::ACTIVE]);
        Division::factory()->create(['status' => DivisionStatus::ARCHIVED]);

        // Act - "inactive" is invalid, "archived" is valid
        // After array_filter: [1 => 'archived'] (key preserved)
        // Bug: accessing $validStatuses[0] when count === 1
        $response = $this->get(self::URI.'?status=inactive,archived');

        // Assert - Should filter archived without "Undefined array key 0" error
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', DivisionStatus::ARCHIVED);
    }
}
