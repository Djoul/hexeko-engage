<?php

namespace Tests\Feature\Http\Controllers\V1\TeamController;

use App\Http\Middleware\CognitoAuthMiddleware;
use App\Models\Team;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('team')]

class FetchTeamByIdTest extends ProtectedRouteTestCase
{
    use WithFaker;

    protected $createTeamAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(CognitoAuthMiddleware::class);
    }

    #[Test]
    public function it_can_fetch_a_single_team(): void
    {
        $team = Team::factory()->create(['name' => 'Team Test']);

        $response = $this->get('/api/v1/teams/'.$team->id);

        $response->assertStatus(200);
    }
}
