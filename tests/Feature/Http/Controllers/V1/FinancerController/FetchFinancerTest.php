<?php

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['financers'], scope: 'test')]
#[Group('financer')]
class FetchFinancerTest extends ProtectedRouteTestCase
{
    use WithFaker;

    const URI = '/api/v1/financers';

    protected function setUp(): void
    {
        parent::setUp();
        // Create the hexeko_admin role if it doesn't exist
        config(['auth.defaults.guard' => 'api']);
        $team = Team::first() ?? ModelFactory::createTeam();
        setPermissionsTeamId($team->id);

        if (! Role::where('name', RoleDefaults::HEXEKO_ADMIN)
            ->where('guard_name', 'api')
            ->where('team_id', $team->id)
            ->exists()) {
            ModelFactory::createRole(['name' => RoleDefaults::HEXEKO_ADMIN, 'guard_name' => 'api', 'team_id' => $team->id]);
        }
    }

    #[Test]
    public function it_can_fetch_all_financer(): void
    {

        $financers = ModelFactory::createFinancer(count: 10);

        // Create a user with full access
        $user = User::factory()->create();
        $user->assignRole(RoleDefaults::HEXEKO_ADMIN);

        // Use the first financer for the x-financer-id header
        $response = $this->actingAs($user)
            ->withHeader('x-financer-id', $financers->first()->id)
            ->get(self::URI);

        $response->assertStatus(200);

        $this->assertDatabaseCount('financers', 10);

    }

    #[Test]
    public function it_includes_new_fields_in_response(): void
    {
        ModelFactory::createFinancer([
            'name' => 'Test Financer',
            'status' => 'active',
            'bic' => 'BNPAFRPPXXX',
            'company_number' => 'BE0123456789',
        ]);

        // Create a user with full access
        $user = User::factory()->create();
        $user->assignRole(RoleDefaults::HEXEKO_ADMIN);

        $response = $this->actingAs($user)
            ->get(self::URI);

        $response->assertStatus(200);

        // Verify the response includes all new fields
        $response->assertJsonPath('data.0.status', 'active');
        $response->assertJsonPath('data.0.bic', 'BNPAFRPPXXX');
        $response->assertJsonPath('data.0.company_number', 'BE0123456789');

        // Also verify available_languages is included
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
    }

    #[Test]
    public function it_includes_pagination_metadata_in_response(): void
    {
        // Create 25 financers to test pagination
        ModelFactory::createFinancer(count: 25);

        // Create a user with full access
        $user = User::factory()->create();
        $user->assignRole(RoleDefaults::HEXEKO_ADMIN);

        // Request with pagination parameters
        $response = $this->actingAs($user)
            ->get(self::URI.'?per_page=10&page=2');

        $response->assertStatus(200);

        // Verify pagination metadata is present
        $response->assertJsonStructure([
            'data',
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'per_page',
                'to',
                'total',
                // Reference metadata should also be present
                'countries',
                'currencies',
                'languages',
                'timezones',
                'statuses',
                'divisions',
                'divisions_array',
                'users',
            ],
        ]);

        // Verify pagination values are correct
        $response->assertJsonPath('meta.current_page', 2);
        $response->assertJsonPath('meta.per_page', 10);
        $response->assertJsonPath('meta.from', 11);
        $response->assertJsonPath('meta.to', 20);
        $response->assertJsonPath('meta.total', 25);
        $response->assertJsonPath('meta.last_page', 3);
    }
}
