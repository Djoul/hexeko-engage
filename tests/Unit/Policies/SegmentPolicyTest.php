<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Models\Financer;
use App\Models\Permission;
use App\Models\Segment;
use App\Models\Team;
use App\Models\User;
use App\Policies\SegmentPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('segment')]
#[Group('policy')]
class SegmentPolicyTest extends TestCase
{
    use DatabaseTransactions;

    private SegmentPolicy $policy;

    private User $user;

    private User $otherUser;

    private Segment $segment;

    private Segment $otherSegment;

    private Financer $financer;

    private Financer $otherFinancer;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new SegmentPolicy;

        $this->team = ModelFactory::createTeam();
        setPermissionsTeamId($this->team->id);

        Permission::firstOrCreate(['name' => PermissionDefaults::READ_SEGMENT, 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => PermissionDefaults::CREATE_SEGMENT, 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => PermissionDefaults::UPDATE_SEGMENT, 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => PermissionDefaults::DELETE_SEGMENT, 'guard_name' => 'api']);

        $this->user = User::factory()->create(['team_id' => $this->team->id]);
        $this->otherUser = User::factory()->create(['team_id' => $this->team->id]);

        $this->financer = Financer::factory()->create();
        $this->otherFinancer = Financer::factory()->create();

        $this->segment = Segment::factory()->create([
            'financer_id' => $this->financer->id,
        ]);

        $this->otherSegment = Segment::factory()->create([
            'financer_id' => $this->otherFinancer->id,
        ]);

        Context::flush();
    }

    protected function tearDown(): void
    {
        Context::flush();
        parent::tearDown();
    }

    #[Test]
    public function user_without_read_permission_cannot_view_any_segments(): void
    {
        // Arrange

        // Act
        $canViewUser = $this->policy->viewAny($this->user);
        $canViewOtherUser = $this->policy->viewAny($this->otherUser);

        // Assert
        $this->assertFalse($canViewUser);
        $this->assertFalse($canViewOtherUser);
    }

    #[Test]
    public function user_with_read_permission_can_view_any_segments(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::READ_SEGMENT);
        $this->otherUser->givePermissionTo(PermissionDefaults::READ_SEGMENT);

        // Act
        $canViewUser = $this->policy->viewAny($this->user);
        $canViewOtherUser = $this->policy->viewAny($this->otherUser);

        // Assert
        $this->assertTrue($canViewUser);
        $this->assertTrue($canViewOtherUser);
    }

    #[Test]
    public function user_without_read_permission_cannot_view_segment(): void
    {
        // Arrange

        // Act
        $canViewUser = $this->policy->view($this->user, $this->segment);
        $canViewOtherUser = $this->policy->view($this->otherUser, $this->otherSegment);

        // Assert
        $this->assertFalse($canViewUser);
        $this->assertFalse($canViewOtherUser);
    }

    #[Test]
    public function user_with_read_permission_can_view_segment_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_SEGMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        Context::add('financer_id', $this->financer->id);

        // Act
        $canView = $this->policy->view($userWithPermission, $this->segment);

        // Assert
        $this->assertTrue($canView);
    }

    #[Test]
    public function user_with_read_permission_cannot_view_segment_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_SEGMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        Context::add('financer_id', $this->financer->id);

        // Act
        $canView = $this->policy->view($userWithPermission, $this->otherSegment);

        // Assert
        $this->assertFalse($canView);
    }

    #[Test]
    public function user_without_create_permission_cannot_create_segment(): void
    {
        // Arrange

        // Act
        $canCreateUser = $this->policy->create($this->user);
        $canCreateOtherUser = $this->policy->create($this->otherUser);

        // Assert
        $this->assertFalse($canCreateUser);
        $this->assertFalse($canCreateOtherUser);
    }

    #[Test]
    public function user_with_create_permission_can_create_segment(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::CREATE_SEGMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        Context::add('financer_id', $this->financer->id);

        // Act
        $canCreate = $this->policy->create($userWithPermission);

        // Assert
        $this->assertTrue($canCreate);
    }

    #[Test]
    public function user_without_update_permission_cannot_update_segment(): void
    {
        // Arrange

        // Act
        $canUpdateUser = $this->policy->update($this->user, $this->segment);
        $canUpdateOtherUser = $this->policy->update($this->otherUser, $this->otherSegment);

        // Assert
        $this->assertFalse($canUpdateUser);
        $this->assertFalse($canUpdateOtherUser);
    }

    #[Test]
    public function user_with_update_permission_can_update_segment_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_SEGMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        Context::add('financer_id', $this->financer->id);

        // Act
        $canUpdate = $this->policy->update($userWithPermission, $this->segment);

        // Assert
        $this->assertTrue($canUpdate);
    }

    #[Test]
    public function user_with_update_permission_cannot_update_segment_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_SEGMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        Context::add('financer_id', $this->financer->id);

        // Act
        $canUpdate = $this->policy->update($userWithPermission, $this->otherSegment);

        // Assert
        $this->assertFalse($canUpdate);
    }

    #[Test]
    public function user_without_delete_permission_cannot_delete_segment(): void
    {
        // Arrange

        // Act
        $canDeleteUser = $this->policy->delete($this->user, $this->segment);
        $canDeleteOtherUser = $this->policy->delete($this->otherUser, $this->otherSegment);

        // Assert
        $this->assertFalse($canDeleteUser);
        $this->assertFalse($canDeleteOtherUser);
    }

    #[Test]
    public function user_with_delete_permission_can_delete_segment_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_SEGMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        Context::add('financer_id', $this->financer->id);

        // Act
        $canDelete = $this->policy->delete($userWithPermission, $this->segment);

        // Assert
        $this->assertTrue($canDelete);
    }

    #[Test]
    public function user_with_delete_permission_cannot_delete_segment_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_SEGMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        Context::add('financer_id', $this->financer->id);

        // Act
        $canDelete = $this->policy->delete($userWithPermission, $this->otherSegment);

        // Assert
        $this->assertFalse($canDelete);
    }
}
