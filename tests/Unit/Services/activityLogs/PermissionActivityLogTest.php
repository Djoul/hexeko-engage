<?php

namespace Tests\Unit\Services\ActivityLogs;

use App\Models\Permission;
use App\Models\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\ProtectedRouteTestCase;

#[Group('permission')]
#[Group('audit')]
class PermissionActivityLogTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! config('activitylog.enabled')) {
            $this->markTestSkipped('Activity logging is not enabled');
        }
    }

    #[Test]
    public function it_logs_when_a_permission_is_created(): void
    {
        $permission = Permission::create([
            'name' => 'edit_posts',
            'guard_name' => 'web',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'permission',
            'description' => 'created',
            'subject_id' => $permission->id,
            'subject_type' => Permission::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_permission_is_updated(): void
    {
        $permission = Permission::factory()->create();

        $permission->update(['name' => 'updated_permission']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'permission',
            'description' => 'updated',
            'subject_id' => $permission->id,
            'subject_type' => Permission::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_permission_is_deleted(): void
    {
        $permission = Permission::factory()->create();

        $permission->delete();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'permission',
            'description' => 'deleted',
            'subject_id' => $permission->id,
            'subject_type' => Permission::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_permission_is_assigned_to_a_role(): void
    {
        $role = Role::factory()->create();

        $permission = Permission::factory()->create();

        $role->givePermissionTo($permission->name);

        $this->assertCount(2, Activity::where('subject_id', $role->id)->get());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'role',
            'description' => 'Permission assigned',
            'subject_id' => $role->id,
            'subject_type' => Role::class,
        ]);
    }

    /* #[Test] */
    public function it_logs_when_a_permission_is_revoked_from_a_role(): void
    {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();

        $role->givePermissionTo($permission->name);
        $role->revokePermissionTo($permission->name);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'role',
            'description' => "Permission revoked from role {$role->name}",
            'subject_id' => $role->id,
            'subject_type' => Role::class,
        ]);
    }
}
