<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Models\Financer;
use App\Models\JobTitle;
use App\Models\Permission;
use App\Models\Team;
use App\Models\User;
use App\Policies\JobTitlePolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('jobtitle')]
#[Group('policy')]
class JobTitlePolicyTest extends TestCase
{
    use DatabaseTransactions;

    private JobTitlePolicy $policy;

    private User $user;

    private User $otherUser;

    private JobTitle $jobTitle;

    private JobTitle $otherJobTitle;

    private Financer $financer;

    private Financer $otherFinancer;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new JobTitlePolicy;

        $this->team = ModelFactory::createTeam();
        setPermissionsTeamId($this->team->id);

        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_JOB_TITLE,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::CREATE_JOB_TITLE,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::UPDATE_JOB_TITLE,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::DELETE_JOB_TITLE,
            'guard_name' => 'api',
        ]);

        $this->user = User::factory()->create(['team_id' => $this->team->id]);
        $this->otherUser = User::factory()->create(['team_id' => $this->team->id]);

        $this->financer = Financer::factory()->create();
        $this->otherFinancer = Financer::factory()->create();

        $this->jobTitle = JobTitle::factory()->create([
            'financer_id' => $this->financer->id,
            'name' => ['en-GB' => 'Software Engineer', 'fr-FR' => 'Ingénieur Logiciel'],
        ]);

        $this->otherJobTitle = JobTitle::factory()->create([
            'financer_id' => $this->otherFinancer->id,
            'name' => ['en-GB' => 'Product Manager', 'fr-FR' => 'Chef de Produit'],
        ]);

        Context::flush();
    }

    protected function tearDown(): void
    {
        Context::flush();
        parent::tearDown();
    }

    #[Test]
    public function user_without_read_job_title_permission_cannot_view_any_job_titles(): void
    {
        // Arrange
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->viewAny($this->user);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_read_job_title_permission_can_view_any_job_titles(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::READ_JOB_TITLE);
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->viewAny($this->user);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function user_without_read_job_title_permission_cannot_view_job_title(): void
    {
        // Arrange
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->view($this->user, $this->jobTitle);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_read_job_title_permission_can_view_job_title_in_same_financer(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::READ_JOB_TITLE);
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->view($this->user, $this->jobTitle);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function user_with_read_job_title_permission_cannot_view_job_title_in_different_financer(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::READ_JOB_TITLE);
        Context::add('financer_id', $this->otherFinancer->id);

        // Act
        $result = $this->policy->view($this->user, $this->jobTitle);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_without_create_job_title_permission_cannot_create_job_titles(): void
    {
        // Arrange
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->create($this->user);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_create_job_title_permission_can_create_job_titles(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::CREATE_JOB_TITLE);
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->create($this->user);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function user_without_update_job_title_permission_cannot_update_job_titles(): void
    {
        // Arrange
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->update($this->user, $this->jobTitle);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_update_job_title_permission_can_update_job_title_in_same_financer(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::UPDATE_JOB_TITLE);
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->update($this->user, $this->jobTitle);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function user_with_update_job_title_permission_cannot_update_job_title_in_different_financer(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::UPDATE_JOB_TITLE);
        Context::add('financer_id', $this->otherFinancer->id);

        // Act
        $result = $this->policy->update($this->user, $this->jobTitle);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_without_delete_job_title_permission_cannot_delete_job_titles(): void
    {
        // Arrange
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->delete($this->user, $this->jobTitle);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_delete_job_title_permission_can_delete_job_title_in_same_financer(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::DELETE_JOB_TITLE);
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->delete($this->user, $this->jobTitle);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function user_with_delete_job_title_permission_cannot_delete_job_title_in_different_financer(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::DELETE_JOB_TITLE);
        Context::add('financer_id', $this->otherFinancer->id);

        // Act
        $result = $this->policy->delete($this->user, $this->jobTitle);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_read_job_title_permission_and_wrong_current_financer_cannot_view_job_titles(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_JOB_TITLE);

        Context::add('financer_id', $this->otherFinancer->id);

        // Act
        $result = $this->policy->view($userWithPermission, $this->jobTitle);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_update_job_title_permission_and_wrong_current_financer_cannot_update_job_titles(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_JOB_TITLE);

        Context::add('financer_id', $this->otherFinancer->id);

        // Act
        $result = $this->policy->update($userWithPermission, $this->jobTitle);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_delete_job_title_permission_and_wrong_current_financer_cannot_delete_job_titles(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_JOB_TITLE);

        Context::add('financer_id', $this->otherFinancer->id);

        // Act
        $result = $this->policy->delete($userWithPermission, $this->jobTitle);

        // Assert
        $this->assertFalse($result);
    }
}
