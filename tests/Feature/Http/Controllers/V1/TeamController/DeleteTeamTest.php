<?php

namespace Tests\Feature\Http\Controllers\V1\TeamController;

use App\Http\Middleware\CognitoAuthMiddleware;
use App\Models\Team;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('team')]

class DeleteTeamTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_can_delete_team(): void
    {
        $this->withoutMiddleware(CognitoAuthMiddleware::class);
        $team = Team::factory()->create();

        $this->assertDatabasehas('teams', ['id' => $team['id'], 'deleted_at' => null]);

        $response = $this->delete("/api/v1/teams/{$team->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('teams', ['id' => $team['id'], 'deleted_at' => null]);
    }
}
