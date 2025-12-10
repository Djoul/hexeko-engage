<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\Survey\Models\Submission;
use App\Integrations\Survey\Models\Survey;
use App\Integrations\Survey\Policies\SubmissionPolicy;
use App\Models\Financer;
use App\Models\Permission;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('survey')]
#[Group('policy')]
#[Group('submission')]
class SubmissionPolicyTest extends TestCase
{
    use DatabaseTransactions;

    private SubmissionPolicy $policy;

    private User $user;

    private User $otherUser;

    private Submission $submission;

    private Submission $otherSubmission;

    private Financer $financer;

    private Financer $otherFinancer;

    private Survey $survey;

    private Survey $otherSurvey;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new SubmissionPolicy;

        // Create team for permissions
        $this->team = ModelFactory::createTeam();
        setPermissionsTeamId($this->team->id);

        // Create permissions
        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_SUBMISSION,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::CREATE_SUBMISSION,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::UPDATE_SUBMISSION,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::DELETE_SUBMISSION,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS,
            'guard_name' => 'api',
        ]);

        // Create users
        $this->user = User::factory()->create(['team_id' => $this->team->id]);
        $this->otherUser = User::factory()->create(['team_id' => $this->team->id]);

        // Create financers
        $this->financer = Financer::factory()->create();
        $this->otherFinancer = Financer::factory()->create();

        // Create surveys
        $this->survey = Survey::create([
            'financer_id' => $this->financer->id,
            'title' => ['en' => 'Test Survey', 'fr' => 'Campagne Test'],
            'description' => ['en' => 'Test Description', 'fr' => 'Description Test'],
            'welcome_message' => ['en' => 'Welcome', 'fr' => 'Bienvenue'],
            'thank_you_message' => ['en' => 'Thank you', 'fr' => 'Merci'],
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'settings' => ['theme' => 'light'],
        ]);

        $this->otherSurvey = Survey::create([
            'financer_id' => $this->otherFinancer->id,
            'title' => ['en' => 'Other Survey', 'fr' => 'Autre Campagne'],
            'description' => ['en' => 'Other Description', 'fr' => 'Autre Description'],
            'welcome_message' => ['en' => 'Welcome', 'fr' => 'Bienvenue'],
            'thank_you_message' => ['en' => 'Thank you', 'fr' => 'Merci'],
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'settings' => ['theme' => 'light'],
        ]);

        // Create submissions
        $this->submission = Submission::factory()->create([
            'user_id' => $this->user->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $this->survey->id,
        ]);

        $this->otherSubmission = Submission::factory()->create([
            'user_id' => $this->otherUser->id,
            'financer_id' => $this->otherFinancer->id,
            'survey_id' => $this->otherSurvey->id,
        ]);

        // Clean context
        Context::flush();

        $this->user->financers()->attach($this->financer->id, ['active' => true]);
        $this->otherUser->financers()->attach($this->financer->id, ['active' => true]);
        Context::add('accessible_financers', [$this->financer->id, $this->otherFinancer->id]);
        Context::add('financer_id', $this->financer->id);
    }

    protected function tearDown(): void
    {
        Context::flush();
        parent::tearDown();
    }

    #[Test]
    public function user_without_read_submission_permission_cannot_view_any_submissions(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->viewAny($this->user));
        $this->assertFalse($this->policy->viewAny($this->otherUser));
    }

    #[Test]
    public function user_with_read_submission_permission_can_view_any_submissions(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::READ_SUBMISSION);
        $this->otherUser->givePermissionTo(PermissionDefaults::READ_SUBMISSION);

        // Act & Assert
        $this->assertTrue($this->policy->viewAny($this->user));
        $this->assertTrue($this->policy->viewAny($this->otherUser));
    }

    #[Test]
    public function user_without_create_submission_permission_cannot_create_submissions(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->create($this->user));
        $this->assertFalse($this->policy->create($this->otherUser));
    }

    #[Test]
    public function user_with_create_submission_permission_can_create_submissions(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::CREATE_SUBMISSION);
        $this->otherUser->givePermissionTo(PermissionDefaults::CREATE_SUBMISSION);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->create($this->user));
        $this->assertTrue($this->policy->create($this->otherUser));
    }

    #[Test]
    public function user_with_read_submission_permission_can_view_own_submission(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::READ_SUBMISSION);

        // Act & Assert
        $this->assertTrue($this->policy->view($this->user, $this->submission));
    }

    #[Test]
    public function user_with_read_submission_permission_cannot_view_other_users_submission(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::READ_SUBMISSION);

        // Act & Assert
        $this->assertFalse($this->policy->view($this->user, $this->otherSubmission));
    }

    #[Test]
    public function user_with_manage_financer_submissions_permission_can_view_submission_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->view($userWithPermission, $this->submission));
    }

    #[Test]
    public function user_with_manage_financer_submissions_permission_cannot_view_submission_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->otherSubmission));
    }

    #[Test]
    public function user_with_update_submission_permission_can_update_own_submission(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::UPDATE_SUBMISSION);

        // Act & Assert
        $this->assertTrue($this->policy->update($this->user, $this->submission));
    }

    #[Test]
    public function user_with_update_submission_permission_cannot_update_other_users_submission(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::UPDATE_SUBMISSION);

        // Act & Assert
        $this->assertFalse($this->policy->update($this->user, $this->otherSubmission));
    }

    #[Test]
    public function user_with_manage_financer_submissions_permission_can_update_submission_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->update($userWithPermission, $this->submission));
    }

    #[Test]
    public function user_with_manage_financer_submissions_permission_cannot_update_submission_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->otherSubmission));
    }

    #[Test]
    public function user_with_delete_submission_permission_can_delete_own_submission(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::DELETE_SUBMISSION);

        // Act & Assert
        $this->assertTrue($this->policy->delete($this->user, $this->submission));
    }

    #[Test]
    public function user_with_delete_submission_permission_cannot_delete_other_users_submission(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::DELETE_SUBMISSION);

        // Act & Assert
        $this->assertFalse($this->policy->delete($this->user, $this->otherSubmission));
    }

    #[Test]
    public function user_with_manage_financer_submissions_permission_can_delete_submission_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->delete($userWithPermission, $this->submission));
    }

    #[Test]
    public function user_with_manage_financer_submissions_permission_cannot_delete_submission_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->otherSubmission));
    }

    #[Test]
    public function user_with_update_submission_permission_can_complete_own_submission(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::UPDATE_SUBMISSION);

        // Act & Assert
        $this->assertTrue($this->policy->complete($this->user, $this->submission));
    }

    #[Test]
    public function user_with_update_submission_permission_cannot_complete_other_users_submission(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::UPDATE_SUBMISSION);

        // Act & Assert
        $this->assertFalse($this->policy->complete($this->user, $this->otherSubmission));
    }

    #[Test]
    public function user_with_manage_financer_submissions_permission_can_complete_submission_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->complete($userWithPermission, $this->submission));
    }

    #[Test]
    public function user_with_manage_financer_submissions_permission_cannot_complete_submission_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->complete($userWithPermission, $this->otherSubmission));
    }

    #[Test]
    public function user_with_standard_permissions_can_only_access_own_submissions(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo([
            PermissionDefaults::READ_SUBMISSION,
            PermissionDefaults::UPDATE_SUBMISSION,
            PermissionDefaults::DELETE_SUBMISSION,
        ]);

        // Act & Assert - can access own submission
        $this->assertTrue($this->policy->view($this->user, $this->submission));
        $this->assertTrue($this->policy->update($this->user, $this->submission));
        $this->assertTrue($this->policy->delete($this->user, $this->submission));
        $this->assertTrue($this->policy->complete($this->user, $this->submission));

        // Act & Assert - cannot access other user's submission
        $this->assertFalse($this->policy->view($this->user, $this->otherSubmission));
        $this->assertFalse($this->policy->update($this->user, $this->otherSubmission));
        $this->assertFalse($this->policy->delete($this->user, $this->otherSubmission));
        $this->assertFalse($this->policy->complete($this->user, $this->otherSubmission));
    }

    #[Test]
    public function user_with_manage_financer_submissions_permission_but_no_active_financer_context_cannot_access_submissions(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS);

        // No context set for activeFinancerID
        Context::flush();

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->submission));
        $this->assertFalse($this->policy->update($userWithPermission, $this->submission));
        $this->assertFalse($this->policy->delete($userWithPermission, $this->submission));
        $this->assertFalse($this->policy->complete($userWithPermission, $this->submission));
    }

    #[Test]
    public function user_with_manage_financer_submissions_permission_and_wrong_financer_context_cannot_access_submissions(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->submission));
        $this->assertFalse($this->policy->update($userWithPermission, $this->submission));
        $this->assertFalse($this->policy->delete($userWithPermission, $this->submission));
        $this->assertFalse($this->policy->complete($userWithPermission, $this->submission));
    }

    #[Test]
    public function user_with_delete_submission_permission_can_restore_submission(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);

        // Act
        $this->user->givePermissionTo(PermissionDefaults::DELETE_SUBMISSION);

        // Assert
        $this->assertTrue($this->policy->restore($this->user, $this->submission));
    }

    #[Test]
    public function user_with_delete_submission_permission_cannot_restore_other_users_submission(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);

        // Act
        $this->user->givePermissionTo(PermissionDefaults::DELETE_SUBMISSION);

        // Assert
        $this->assertFalse($this->policy->restore($this->user, $this->otherSubmission));
    }

    #[Test]
    public function user_with_delete_submission_permission_cannot_force_delete_submission(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);

        // Act
        $this->user->givePermissionTo(PermissionDefaults::DELETE_SUBMISSION);

        // Assert
        $this->assertFalse($this->policy->forceDelete($this->user));
    }
}
