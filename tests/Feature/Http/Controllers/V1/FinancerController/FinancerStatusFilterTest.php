<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use App\Enums\FinancerStatus;
use App\Enums\IDP\RoleDefaults;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['financers'], scope: 'class')]
#[Group('financer')]
#[Group('filters')]
class FinancerStatusFilterTest extends ProtectedRouteTestCase
{
    private const URI = '/api/v1/financers';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean context before each test
        Context::flush();

        // Setup authentication
        config(['auth.defaults.guard' => 'api']);
        $team = Team::first() ?? ModelFactory::createTeam();
        // Ensure role exists for this team before assigning (avoid duplicate creation)
        if (! Role::where('team_id', $team->id)
            ->where('name', RoleDefaults::HEXEKO_ADMIN)
            ->where('guard_name', 'api')
            ->exists()) {
            ModelFactory::createRole(['name' => RoleDefaults::HEXEKO_ADMIN, 'team_id' => $team->id, 'guard_name' => 'api']);
        }

        // Create a user with full access
        $this->user = ModelFactory::createUser();
        setPermissionsTeamId($team->id);
        $this->user->assignRole(RoleDefaults::HEXEKO_ADMIN);

        // Create a division for financers
        Division::factory()->create();
    }

    protected function tearDown(): void
    {
        // Clean context after each test
        Context::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_filters_financers_by_single_status(): void
    {
        $division = Division::factory()->create();
        $initialCount = Financer::where('division_id', $division->id)->count();

        // Arrange

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ACTIVE,
            'name' => 'Active Financer',
            'deleted_at' => null,
        ]);

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::PENDING,
            'name' => 'Pending Financer',
        ]);

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ARCHIVED,
            'name' => 'Archived Financer',
        ]);
        Context::add('accessible_financers', Financer::where('division_id', $division->id)->pluck('id')->toArray());
        Context::add('accessible_divisions', [$division->id]);
        // Act
        $response = $this->actingAs($this->user)
            ->get(self::URI.'?division_id='.$division->id.'&status=active');
        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount($initialCount + 1, 'data'); // activeFinancer from setUp + new activeFinancer

        $statuses = collect($response->json('data'))->pluck('status')->unique()->toArray();
        $this->assertEquals([FinancerStatus::ACTIVE], $statuses);
    }

    #[Test]
    public function it_filters_financers_by_multiple_statuses(): void
    {
        // Arrange
        $division = Division::factory()->create();

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ACTIVE,
            'name' => 'Active Financer',
        ]);

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::PENDING,
            'name' => 'Pending Financer',
        ]);

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ARCHIVED,
            'name' => 'Archived Financer',
        ]);

        Context::add('accessible_financers', Financer::pluck('id')->toArray());
        Context::add('accessible_divisions', Division::pluck('id')->toArray());

        // Act
        $response = $this->actingAs($this->user)
            ->get(self::URI.'?status=active,pending');

        // Assert
        $response->assertStatus(200);

        // Count financers with active or pending status
        $expectedCount = Financer::whereIn('status', [FinancerStatus::ACTIVE, FinancerStatus::PENDING])->count();
        $response->assertJsonCount($expectedCount, 'data');

        $statuses = collect($response->json('data'))->pluck('status')->unique()->sort()->values()->toArray();
        $this->assertEquals([FinancerStatus::ACTIVE, FinancerStatus::PENDING], $statuses);
    }

    #[Test]
    public function it_returns_all_financers_when_no_status_filter_is_provided(): void
    {
        // Arrange
        $division = Division::factory()->create();

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ACTIVE,
        ]);

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::PENDING,
        ]);

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ARCHIVED,
        ]);

        // Act
        $expectedCount = Financer::count(); // Get actual count for scope='class'

        $response = $this->actingAs($this->user)

            ->get(self::URI);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount($expectedCount, 'data');
    }

    #[Test]
    public function it_ignores_invalid_status_values(): void
    {
        // Arrange
        $division = Division::factory()->create();

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ACTIVE,
            'name' => 'Active Financer',
        ]);

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::PENDING,
            'name' => 'Pending Financer',
        ]);

        Context::add('accessible_financers', Financer::pluck('id')->toArray());
        Context::add('accessible_divisions', Division::pluck('id')->toArray());

        // Act
        $response = $this->actingAs($this->user)
            ->get(self::URI.'?status=active,invalid_status');

        // Assert
        $response->assertStatus(200);

        // Count only active financers (invalid_status should be ignored)
        $expectedCount = Financer::where('status', FinancerStatus::ACTIVE)->count();
        $response->assertJsonCount($expectedCount, 'data');

        $statuses = collect($response->json('data'))->pluck('status')->unique()->toArray();
        $this->assertEquals([FinancerStatus::ACTIVE], $statuses);
    }

    #[Test]
    public function it_handles_empty_status_parameter(): void
    {
        // Arrange
        $division = Division::factory()->create();

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ACTIVE,
        ]);

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::PENDING,
        ]);

        // Act
        $expectedCount = Financer::count(); // Get actual count for scope='class'

        $response = $this->actingAs($this->user)

            ->get(self::URI.'?status=');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount($expectedCount, 'data'); // All financers
    }

    #[Test]
    public function it_combines_status_filter_with_other_filters(): void
    {
        // Arrange
        $division = Division::factory()->create();

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ACTIVE,
            'name' => 'Active Bank ABC',
        ]);

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ACTIVE,
            'name' => 'Active Bank XYZ',
        ]);

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::PENDING,
            'name' => 'Pending Bank ABC',
        ]);

        // Act
        $response = $this->actingAs($this->user)

            ->get(self::URI.'?status=active&search=ABC');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Active Bank ABC');
        $response->assertJsonPath('data.0.status', FinancerStatus::ACTIVE);
    }

    #[Test]
    public function it_filters_all_status_values(): void
    {
        // Arrange
        $division = Division::factory()->create();

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ACTIVE,
            'name' => 'Active Financer',
        ]);

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::PENDING,
            'name' => 'Pending Financer',
        ]);

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ARCHIVED,
            'name' => 'Archived Financer',
        ]);

        // Act
        $expectedCount = Financer::whereIn('status', [
            FinancerStatus::ACTIVE,
            FinancerStatus::PENDING,
            FinancerStatus::ARCHIVED,
        ])->count(); // Get actual count for scope='class'

        $response = $this->actingAs($this->user)

            ->get(self::URI.'?status=active,pending,archived');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount($expectedCount, 'data');

        $statuses = collect($response->json('data'))->pluck('status')->unique()->sort()->values()->toArray();
        $this->assertEquals([
            FinancerStatus::ACTIVE,
            FinancerStatus::ARCHIVED,
            FinancerStatus::PENDING,
        ], $statuses);
    }

    #[Test]
    public function it_handles_status_with_spaces(): void
    {
        // Arrange
        $division = Division::factory()->create();

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ACTIVE,
        ]);

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::PENDING,
        ]);

        Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ARCHIVED,
        ]);

        Context::add('accessible_financers', Financer::pluck('id')->toArray());
        Context::add('accessible_divisions', Division::pluck('id')->toArray());

        // Act
        $response = $this->actingAs($this->user)
            ->get(self::URI.'?status='.urlencode(' active , pending '));

        // Assert
        $response->assertStatus(200);

        // Count financers with active or pending status
        $expectedCount = Financer::whereIn('status', [FinancerStatus::ACTIVE, FinancerStatus::PENDING])->count();
        $response->assertJsonCount($expectedCount, 'data');

        $statuses = collect($response->json('data'))->pluck('status')->unique()->sort()->values()->toArray();
        $this->assertEquals([FinancerStatus::ACTIVE, FinancerStatus::PENDING], $statuses);
    }

    #[Test]
    public function it_works_with_status_filter(): void
    {
        // Arrange
        $division = Division::factory()->create();

        Financer::factory()->count(15)->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ACTIVE,
        ]);

        Financer::factory()->count(5)->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::PENDING,
        ]);

        Context::add('accessible_financers', Financer::pluck('id')->toArray());
        Context::add('accessible_divisions', Division::pluck('id')->toArray());

        // Act
        $response = $this->actingAs($this->user)
            ->get(self::URI.'?status=active');

        // Assert
        $response->assertStatus(200);

        // Count all active financers in the database
        $expectedCount = Financer::where('status', FinancerStatus::ACTIVE)->count();
        $response->assertJsonCount($expectedCount, 'data');

        // Verify all returned items are active
        $statuses = collect($response->json('data'))->pluck('status')->unique()->toArray();
        $this->assertEquals([FinancerStatus::ACTIVE], $statuses);
    }

    #[Test]
    public function it_includes_status_field_in_response(): void
    {
        // Arrange
        $division = Division::factory()->create();

        $testFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::PENDING,
            'name' => 'Test Financer',
            'bic' => 'TESTBIC123',
            'company_number' => 'BE9876543210',
        ]);

        Context::add('accessible_financers', Financer::pluck('id')->toArray());
        Context::add('accessible_divisions', Division::pluck('id')->toArray());

        // Act
        $response = $this->actingAs($this->user)
            ->get(self::URI.'?status=pending');

        // Assert
        $response->assertStatus(200);

        // Count only pending financers
        $expectedCount = Financer::where('status', FinancerStatus::PENDING)->count();
        $response->assertJsonCount($expectedCount, 'data');

        // Verify the response includes all fields including status
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'status',
                    'bic',
                    'company_number',
                    'available_languages',
                ],
            ],
        ]);

        // Find the specific financer we created in the response
        $financerData = collect($response->json('data'))->firstWhere('id', $testFinancer->id);
        $this->assertNotNull($financerData);
        $this->assertEquals(FinancerStatus::PENDING, $financerData['status']);
        $this->assertEquals('Test Financer', $financerData['name']);
    }
}
