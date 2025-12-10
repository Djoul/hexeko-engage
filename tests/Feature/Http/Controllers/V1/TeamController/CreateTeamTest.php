<?php

namespace Tests\Feature\Http\Controllers\V1\TeamController;

use DB;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('team')]

class CreateTeamTest extends ProtectedRouteTestCase
{
    use WithFaker;

    protected $createTeamAction;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_can_create_team(): void
    {
        $this->withoutExceptionHandling();

        // Get the initial count of teams
        $initialCount = DB::table('teams')->count();

        $teamData = ModelFactory::makeTeam(['name' => 'Team Test'])->toArray();

        $response = $this->post('/api/v1/teams', $teamData);

        $response->assertStatus(201);

        $this->assertDatabaseCount('teams', $initialCount + 1);

        $this->assertDatabaseHas('teams', ['name' => $teamData['name']]);
    }
}
