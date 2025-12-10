<?php

namespace Tests\Unit\Services\ActivityLogs;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class UserActivityLogTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! config('activitylog.enabled')) {
            $this->markTestSkipped('Activity logging is not enabled');
        }
    }

    #[Test]
    public function it_logs_when_a_user_is_created(): void
    {
        $user = User::create([
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'User',
            'description' => 'created',
            'subject_id' => $user->id,
            'subject_type' => User::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_user_is_updated(): void
    {
        $user = User::factory()->create();

        $user->update(['first_name' => 'Updated']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'User',
            'description' => 'updated',
            'subject_id' => $user->id,
            'subject_type' => User::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_user_is_deleted(): void
    {
        $user = User::factory()->create();

        $user->delete();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'User',
            'description' => 'deleted',
            'subject_id' => $user->id,
            'subject_type' => User::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_role_is_assigned_to_a_user(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create(['team_id' => $team->id]);
        $role = Role::factory()->create(['team_id' => $team->id]);

        setPermissionsTeamId($team->id);
        $user->assignRole($role->name);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'User',
            'description' => 'Role assigned',
            'subject_id' => $user->id,
            'subject_type' => User::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_role_is_revoked_from_a_user(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create(['team_id' => $team->id]);
        $role = Role::factory()->create(['team_id' => $team->id]);

        setPermissionsTeamId($team->id);

        $user->assignRole($role->name);
        $user->removeRole($role->name);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'User',
            'description' => 'Role Detached',
            'subject_id' => $user->id,
            'subject_type' => User::class,
        ]);
    }
}
