<?php

namespace Tests\Feature\Http\Controllers\V1\TeamController;

use App\Http\Middleware\CognitoAuthMiddleware;
use App\Models\Team;
use DB;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('team')]

class FetchTeamTest extends ProtectedRouteTestCase
{
    use WithFaker;

    const URI = '/api/v1/teams';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(CognitoAuthMiddleware::class);
    }

    #[Test]
    public function it_can_fetch_all_team(): void
    {

        // Get the initial count of teams
        $initialCount = DB::table('teams')->count();

        Team::factory()->count(10)->create();

        $response = $this->get(self::URI);

        $response->assertStatus(200);

        $this->assertDatabaseCount('teams', $initialCount + 10);

    }
}
