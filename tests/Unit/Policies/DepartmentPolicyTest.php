<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Models\Department;
use App\Models\Financer;
use App\Models\Permission;
use App\Models\Team;
use App\Models\User;
use App\Policies\DepartmentPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('department')]
#[Group('policy')]
class DepartmentPolicyTest extends TestCase
{
    use DatabaseTransactions;

    private DepartmentPolicy $policy;

    private User $user;

    private User $otherUser;

    private Department $department;

    private Department $otherDepartment;

    private Financer $financer;

    private Financer $otherFinancer;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new DepartmentPolicy;

        // Create team for permissions
        $this->team = ModelFactory::createTeam();
        setPermissionsTeamId($this->team->id);

        // Create permissions
        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_DEPARTMENT,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::CREATE_DEPARTMENT,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::UPDATE_DEPARTMENT,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::DELETE_DEPARTMENT,
            'guard_name' => 'api',
        ]);

        // Create users
        $this->user = User::factory()->create(['team_id' => $this->team->id]);
        $this->otherUser = User::factory()->create(['team_id' => $this->team->id]);

        // Create financers
        $this->financer = Financer::factory()->create();
        $this->otherFinancer = Financer::factory()->create();

        // Create departments
        $this->department = Department::factory()->create([
            'financer_id' => $this->financer->id,
            'name' => ['en-GB' => 'Test Department', 'fr-FR' => 'Département Test'],
        ]);

        $this->otherDepartment = Department::factory()->create([
            'financer_id' => $this->otherFinancer->id,
            'name' => ['en-GB' => 'Other Department', 'fr-FR' => 'Autre Département'],
        ]);

        // Clean context
        Context::flush();
    }

    protected function tearDown(): void
    {
        Context::flush();
        parent::tearDown();
    }

    #[Test]
    public function user_without_read_department_permission_cannot_view_any_departments(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->viewAny($this->user));
        $this->assertFalse($this->policy->viewAny($this->otherUser));
    }

    #[Test]
    public function user_with_read_department_permission_can_view_any_departments(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::READ_DEPARTMENT);
        $this->otherUser->givePermissionTo(PermissionDefaults::READ_DEPARTMENT);

        // Act & Assert
        $this->assertTrue($this->policy->viewAny($this->user));
        $this->assertTrue($this->policy->viewAny($this->otherUser));
    }

    #[Test]
    public function user_without_read_department_permission_cannot_view_departments(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->view($this->user, $this->department));
        $this->assertFalse($this->policy->view($this->otherUser, $this->otherDepartment));
    }

    #[Test]
    public function user_with_read_department_permission_can_view_department_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_DEPARTMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for currentFinancerId
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->view($userWithPermission, $this->department));
    }

    #[Test]
    public function user_with_read_department_permission_cannot_view_department_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_DEPARTMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for currentFinancerId
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->otherDepartment));
    }

    #[Test]
    public function user_without_create_department_permission_cannot_create_departments(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->create($this->user));
        $this->assertFalse($this->policy->create($this->otherUser));
    }

    #[Test]
    public function user_with_create_department_permission_can_create_departments(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::CREATE_DEPARTMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for currentFinancerId
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->create($userWithPermission));
    }

    #[Test]
    public function user_without_update_department_permission_cannot_update_departments(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->update($this->user, $this->department));
        $this->assertFalse($this->policy->update($this->otherUser, $this->otherDepartment));
    }

    #[Test]
    public function user_with_update_department_permission_can_update_department_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_DEPARTMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for currentFinancerId
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->update($userWithPermission, $this->department));
    }

    #[Test]
    public function user_with_update_department_permission_cannot_update_department_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_DEPARTMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for currentFinancerId
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->otherDepartment));
    }

    #[Test]
    public function user_without_delete_department_permission_cannot_delete_departments(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->delete($this->user, $this->department));
        $this->assertFalse($this->policy->delete($this->otherUser, $this->otherDepartment));
    }

    #[Test]
    public function user_with_delete_department_permission_can_delete_department_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_DEPARTMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for currentFinancerId
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->delete($userWithPermission, $this->department));
    }

    #[Test]
    public function user_with_delete_department_permission_cannot_delete_department_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_DEPARTMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for currentFinancerId
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->otherDepartment));
    }

    #[Test]
    public function user_without_permissions_cannot_access_departments(): void
    {
        // Act & Assert - cannot access any department operations
        $this->assertFalse($this->policy->view($this->user, $this->department));
        $this->assertFalse($this->policy->create($this->user));
        $this->assertFalse($this->policy->update($this->user, $this->department));
        $this->assertFalse($this->policy->delete($this->user, $this->department));
    }

    #[Test]
    public function user_with_read_department_permission_but_no_current_financer_cannot_view_departments(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_DEPARTMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // No context set for currentFinancerId

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->department));
    }

    #[Test]
    public function user_with_update_department_permission_but_no_current_financer_cannot_update_departments(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_DEPARTMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // No context set for currentFinancerId

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->department));
    }

    #[Test]
    public function user_with_delete_department_permission_but_no_current_financer_cannot_delete_departments(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_DEPARTMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // No context set for currentFinancerId

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->department));
    }

    #[Test]
    public function user_with_read_department_permission_and_wrong_current_financer_cannot_view_departments(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_DEPARTMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->department));
    }

    #[Test]
    public function user_with_update_department_permission_and_wrong_current_financer_cannot_update_departments(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_DEPARTMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->department));
    }

    #[Test]
    public function user_with_delete_department_permission_and_wrong_current_financer_cannot_delete_departments(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_DEPARTMENT);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->department));
    }
}
