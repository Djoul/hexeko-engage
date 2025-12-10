<?php

namespace Tests\Unit\Services\ActivityLogs;

use App\Enums\IDP\TeamTypes;
use App\Models\Team;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('team')]

#[Group('audit')]
class TeamActivityLogTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! config('activitylog.enabled')) {
            $this->markTestSkipped('Activity logging is not enabled');
        }
    }

    #[Test]
    public function it_logs_when_a_team_is_created(): void
    {
        $team = Team::create([
            'name' => 'Engineering',
            'slug' => 'engineering',
            'type' => TeamTypes::GLOBAL,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'team',
            'description' => 'created',
            'subject_id' => $team->id,
            'subject_type' => Team::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_team_is_updated(): void
    {
        $team = Team::factory()->create();

        $team->update(['name' => 'Updated Team']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'team',
            'description' => 'updated',
            'subject_id' => $team->id,
            'subject_type' => Team::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_team_is_deleted(): void
    {
        $team = Team::factory()->create();

        $team->delete();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'team',
            'description' => 'deleted',
            'subject_id' => $team->id,
            'subject_type' => Team::class,
        ]);
    }
}
