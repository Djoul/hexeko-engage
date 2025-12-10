<?php

namespace Tests\Unit\Services\ActivityLogs;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('role')]

#[Group('audit')]
class RoleActivityLogTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! config('activitylog.enabled')) {
            $this->markTestSkipped('Activity logging is not enabled');
        }

        // Delete dependent tables first to avoid foreign key constraints
        DB::table('role_has_permissions')->delete();
        DB::table('model_has_roles')->delete();
        DB::table('roles')->delete();
    }

    #[Test]
    public function it_logs_when_a_role_is_created(): void
    {
        $role = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'role',
            'description' => 'created',
            'subject_id' => $role->id,
            'subject_type' => Role::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_role_is_updated(): void
    {
        $role = Role::factory()->create();

        // Ensure we're updating to a different name than what was created
        $newName = $role->name === 'super_admin' ? 'different_admin' : 'super_admin';

        $role->update(['name' => $newName]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'role',
            'description' => 'updated',
            'subject_id' => $role->id,
            'subject_type' => Role::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_role_is_deleted(): void
    {
        $role = Role::factory()->create();

        $role->delete();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'role',
            'description' => 'deleted',
            'subject_id' => $role->id,
            'subject_type' => Role::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_role_is_assigned_to_a_user(): void
    {
        $team = Team::factory()->create();
        $role = Role::factory()->create(['team_id' => $team->id]);
        $user = User::factory()->create(['team_id' => $team->id]);

        setPermissionsTeamId($team->id);

        $role->assignUser($user);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'role',
            'description' => "Role {$role->name} assigned to user {$user->id}",
            'subject_id' => $role->id,
            'subject_type' => Role::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_role_is_revoked_from_a_user(): void
    {
        $team = Team::factory()->create();
        $role = Role::factory()->create(['team_id' => $team->id]);
        $user = User::factory()->create(['team_id' => $team->id]);

        setPermissionsTeamId($team->id);

        $role->assignUser($user);
        $role->revokeUser($user);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'role',
            'description' => "Role {$role->name} revoked from user {$user->id}",
            'subject_id' => $role->id,
            'subject_type' => Role::class,
        ]);
    }
}
