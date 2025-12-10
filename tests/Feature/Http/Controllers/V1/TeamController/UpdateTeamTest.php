<?php

namespace Tests\Feature\Http\Controllers\V1\TeamController;

use App\Models\Team;
use DB;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('team')]

class UpdateTeamTest extends ProtectedRouteTestCase
{
    use WithFaker;

    const URI = '/api/v1/teams/';

    #[Test]
    public function it_can_update_team(): void
    {
        // Get the initial count of teams
        $initialCount = DB::table('teams')->count();

        $team = Team::factory()->create(
            ['name' => 'Team Test', 'slug' => 'team-test']
        );

        $updatedData = [
            ...$team->toArray(),
            'name' => 'Updated Team Test',
        ];

        $this->assertDatabaseCount('teams', $initialCount + 1);
        $response = $this->put(self::URI."{$team->id}", $updatedData, ['Accept' => 'application/json']);

        $response->assertStatus(200);

        $this->assertDatabaseCount('teams', $initialCount + 1);
        $this->assertDatabaseHas('teams', ['id' => $team['id'], 'name' => $updatedData['name']]);

    }
}
