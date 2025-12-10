<?php

namespace Tests\Unit\Models;

use App\Enums\IDP\TeamTypes;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('user')]
#[Group('role')]
#[Group('team')]
class UserTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_returns_global_team_id_when_team_id_is_null(): void
    {
        // Arrange - Ensure global team exists
        $globalTeam = Team::whereType(TeamTypes::GLOBAL)->first();
        if (! $globalTeam) {
            /** @var Team $globalTeam */
            $globalTeam = Team::factory()->create(['type' => TeamTypes::GLOBAL]);
        }

        // Create user with NULL team_id
        /** @var User $user */
        $user = User::factory()->create(['team_id' => null]);

        // Force refresh to ensure we're reading from accessor
        $user->refresh();

        // Act
        $teamId = $user->team_id;

        // Assert
        $this->assertNotNull($teamId, 'team_id accessor should never return null');
        $this->assertEquals($globalTeam->id, $teamId, 'team_id accessor should return global team ID when database value is null');
    }

    #[Test]
    public function it_preserves_existing_team_id_when_not_null(): void
    {
        // Arrange
        /** @var Team $customTeam */
        $customTeam = Team::factory()->create();

        // Create user with specific team_id
        /** @var User $user */
        $user = User::factory()->create(['team_id' => $customTeam->id]);

        // Act
        $teamId = $user->team_id;

        // Assert
        $this->assertEquals($customTeam->id, $teamId, 'team_id accessor should preserve existing non-null value');
    }

    #[Test]
    public function it_sets_global_team_id_when_setting_null(): void
    {
        // Arrange - Ensure global team exists
        $globalTeam = Team::whereType(TeamTypes::GLOBAL)->first();
        if (! $globalTeam) {
            /** @var Team $globalTeam */
            $globalTeam = Team::factory()->create(['type' => TeamTypes::GLOBAL]);
        }

        /** @var User $user */
        $user = User::factory()->create();

        // Act
        $user->team_id = null;
        $user->save();
        $user->refresh();

        // Assert
        $this->assertEquals($globalTeam->id, $user->team_id, 'Setting team_id to null should store global team ID');
    }
}
