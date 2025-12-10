<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\Survey\Models\Answer;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Submission;
use App\Integrations\Survey\Models\Survey;
use App\Integrations\Survey\Policies\AnswerPolicy;
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
#[Group('answer')]
class AnswerPolicyTest extends TestCase
{
    use DatabaseTransactions;

    private AnswerPolicy $policy;

    private User $user;

    private User $otherUser;

    private Answer $answer;

    private Answer $otherAnswer;

    private Financer $financer;

    private Financer $otherFinancer;

    private Survey $survey;

    private Survey $otherSurvey;

    private Submission $submission;

    private Submission $otherSubmission;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new AnswerPolicy;

        // Create team for permissions
        $this->team = ModelFactory::createTeam();
        setPermissionsTeamId($this->team->id);

        // Create permissions
        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_ANSWER,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::CREATE_ANSWER,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::UPDATE_ANSWER,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::DELETE_ANSWER,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::MANAGE_FINANCER_ANSWERS,
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

        // Create questions
        $question = Question::factory()->create([
            'financer_id' => $this->financer->id,
        ]);

        $otherQuestion = Question::factory()->create([
            'financer_id' => $this->otherFinancer->id,
        ]);

        // Create answers
        $this->answer = Answer::factory()->create([
            'user_id' => $this->user->id,
            'submission_id' => $this->submission->id,
            'question_id' => $question->id,
        ]);

        $this->otherAnswer = Answer::factory()->create([
            'user_id' => $this->otherUser->id,
            'submission_id' => $this->otherSubmission->id,
            'question_id' => $otherQuestion->id,
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
    public function user_without_read_answer_permission_cannot_view_any_answers(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->viewAny($this->user));
        $this->assertFalse($this->policy->viewAny($this->otherUser));
    }

    #[Test]
    public function user_with_read_answer_permission_can_view_any_answers(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::READ_ANSWER);
        $this->otherUser->givePermissionTo(PermissionDefaults::READ_ANSWER);

        // Act & Assert
        $this->assertTrue($this->policy->viewAny($this->user));
        $this->assertTrue($this->policy->viewAny($this->otherUser));
    }

    #[Test]
    public function user_without_create_answer_permission_cannot_create_answers(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->create($this->user));
        $this->assertFalse($this->policy->create($this->otherUser));
    }

    #[Test]
    public function user_with_create_answer_permission_can_create_answers(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::CREATE_ANSWER);
        $this->otherUser->givePermissionTo(PermissionDefaults::CREATE_ANSWER);

        // Act & Assert
        $this->assertTrue($this->policy->create($this->user));
        $this->assertTrue($this->policy->create($this->otherUser));
    }

    #[Test]
    public function user_with_read_answer_permission_can_view_own_answer(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::READ_ANSWER);

        // Act & Assert
        $this->assertTrue($this->policy->view($this->user, $this->answer));
    }

    #[Test]
    public function user_with_read_answer_permission_cannot_view_other_users_answer(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::READ_ANSWER);

        // Act & Assert
        $this->assertFalse($this->policy->view($this->user, $this->otherAnswer));
    }

    #[Test]
    public function user_with_manage_financer_answers_permission_can_view_answer_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_ANSWERS);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Act & Assert
        $this->assertTrue($this->policy->view($userWithPermission, $this->answer));
    }

    #[Test]
    public function user_with_manage_financer_answers_permission_cannot_view_answer_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_ANSWERS);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->otherAnswer));
    }

    #[Test]
    public function user_with_update_answer_permission_can_update_own_answer(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::UPDATE_ANSWER);

        // Act & Assert
        $this->assertTrue($this->policy->update($this->user, $this->answer));
    }

    #[Test]
    public function user_with_update_answer_permission_cannot_update_other_users_answer(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::UPDATE_ANSWER);

        // Act & Assert
        $this->assertFalse($this->policy->update($this->user, $this->otherAnswer));
    }

    #[Test]
    public function user_with_manage_financer_answers_permission_can_update_answer_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_ANSWERS);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Act & Assert
        $this->assertTrue($this->policy->update($userWithPermission, $this->answer));
    }

    #[Test]
    public function user_with_manage_financer_answers_permission_cannot_update_answer_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_ANSWERS);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->otherAnswer));
    }

    #[Test]
    public function user_with_delete_answer_permission_can_delete_own_answer(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::DELETE_ANSWER);

        // Act & Assert
        $this->assertTrue($this->policy->delete($this->user, $this->answer));
    }

    #[Test]
    public function user_with_delete_answer_permission_cannot_delete_other_users_answer(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::DELETE_ANSWER);

        // Act & Assert
        $this->assertFalse($this->policy->delete($this->user, $this->otherAnswer));
    }

    #[Test]
    public function user_with_manage_financer_answers_permission_can_delete_answer_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_ANSWERS);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Act & Assert
        $this->assertTrue($this->policy->delete($userWithPermission, $this->answer));
    }

    #[Test]
    public function user_with_manage_financer_answers_permission_cannot_delete_answer_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_ANSWERS);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->otherAnswer));
    }

    #[Test]
    public function user_with_update_answer_permission_can_complete_own_answer(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::UPDATE_ANSWER);

        // Act & Assert
        $this->assertTrue($this->policy->complete($this->user, $this->answer));
    }

    #[Test]
    public function user_with_update_answer_permission_cannot_complete_other_users_answer(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::UPDATE_ANSWER);

        // Act & Assert
        $this->assertFalse($this->policy->complete($this->user, $this->otherAnswer));
    }

    #[Test]
    public function user_with_manage_financer_answers_permission_can_complete_answer_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_ANSWERS);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Act & Assert
        $this->assertTrue($this->policy->complete($userWithPermission, $this->answer));
    }

    #[Test]
    public function user_with_manage_financer_answers_permission_cannot_complete_answer_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_ANSWERS);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Act & Assert
        $this->assertFalse($this->policy->complete($userWithPermission, $this->otherAnswer));
    }

    #[Test]
    public function user_with_standard_permissions_can_only_access_own_answers(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo([
            PermissionDefaults::READ_ANSWER,
            PermissionDefaults::UPDATE_ANSWER,
            PermissionDefaults::DELETE_ANSWER,
        ]);

        // Act & Assert - can access own answer
        $this->assertTrue($this->policy->view($this->user, $this->answer));
        $this->assertTrue($this->policy->update($this->user, $this->answer));
        $this->assertTrue($this->policy->delete($this->user, $this->answer));
        $this->assertTrue($this->policy->complete($this->user, $this->answer));

        // Act & Assert - cannot access other user's answer
        $this->assertFalse($this->policy->view($this->user, $this->otherAnswer));
        $this->assertFalse($this->policy->update($this->user, $this->otherAnswer));
        $this->assertFalse($this->policy->delete($this->user, $this->otherAnswer));
        $this->assertFalse($this->policy->complete($this->user, $this->otherAnswer));
    }

    #[Test]
    public function user_with_manage_financer_answers_permission_but_no_active_financer_context_cannot_access_answers(): void
    {
        // Arrange
        Context::flush(); // Clean any context from previous tests
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_ANSWERS);
        // User has NO financer attached - so activeFinancerID() will return null in console or abort in web context

        // No context set for activeFinancerID
        Context::flush();

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->answer));
        $this->assertFalse($this->policy->update($userWithPermission, $this->answer));
        $this->assertFalse($this->policy->delete($userWithPermission, $this->answer));
        $this->assertFalse($this->policy->complete($userWithPermission, $this->answer));
    }

    #[Test]
    public function user_with_manage_financer_answers_permission_and_wrong_financer_context_cannot_access_answers(): void
    {
        // Arrange
        Context::flush(); // Clean any context from previous tests
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_ANSWERS);
        // Attach a DIFFERENT financer (otherFinancer) - so activeFinancerID() returns otherFinancer, not this->financer
        $userWithPermission->financers()->attach($this->otherFinancer->id, ['active' => true]);

        // Act & Assert - user tries to access answer from $this->financer but has access to $this->otherFinancer
        $this->assertFalse($this->policy->view($userWithPermission, $this->answer));
        $this->assertFalse($this->policy->update($userWithPermission, $this->answer));
        $this->assertFalse($this->policy->delete($userWithPermission, $this->answer));
        $this->assertFalse($this->policy->complete($userWithPermission, $this->answer));
    }

    #[Test]
    public function user_with_delete_answer_permission_can_restore_answer(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);

        // Act
        $this->user->givePermissionTo(PermissionDefaults::DELETE_ANSWER);

        // Assert
        $this->assertTrue($this->policy->restore($this->user, $this->answer));
    }

    #[Test]
    public function user_with_delete_answer_permission_cannot_restore_other_users_answer(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);

        // Act
        $this->user->givePermissionTo(PermissionDefaults::DELETE_ANSWER);

        // Assert
        $this->assertFalse($this->policy->restore($this->user, $this->otherAnswer));
    }

    #[Test]
    public function user_with_delete_answer_permission_cannot_force_delete_answer(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);

        // Act
        $this->user->givePermissionTo(PermissionDefaults::DELETE_ANSWER);

        // Assert
        $this->assertFalse($this->policy->forceDelete($this->user));
    }
}
