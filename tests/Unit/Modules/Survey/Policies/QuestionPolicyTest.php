<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Policies\QuestionPolicy;
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
#[Group('question')]
class QuestionPolicyTest extends TestCase
{
    use DatabaseTransactions;

    private QuestionPolicy $policy;

    private User $user;

    private User $otherUser;

    private Question $question;

    private Question $otherQuestion;

    private Financer $financer;

    private Financer $otherFinancer;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new QuestionPolicy;

        // Create team for permissions
        $this->team = ModelFactory::createTeam();
        setPermissionsTeamId($this->team->id);

        // Create permissions
        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_QUESTION,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::CREATE_QUESTION,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::UPDATE_QUESTION,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::DELETE_QUESTION,
            'guard_name' => 'api',
        ]);

        // Create users
        $this->user = User::factory()->create(['team_id' => $this->team->id]);
        $this->otherUser = User::factory()->create(['team_id' => $this->team->id]);

        // Create financers
        $this->financer = Financer::factory()->create();
        $this->otherFinancer = Financer::factory()->create();

        // Create questions
        $this->question = Question::factory()->create([
            'financer_id' => $this->financer->id,
            'text' => ['en' => 'Test Question', 'fr' => 'Question Test'],
            'help_text' => ['en' => 'Test Help', 'fr' => 'Aide Test'],
        ]);

        $this->otherQuestion = Question::factory()->create([
            'financer_id' => $this->otherFinancer->id,
            'text' => ['en' => 'Other Question', 'fr' => 'Autre Question'],
            'help_text' => ['en' => 'Other Help', 'fr' => 'Autre Aide'],
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
    public function user_without_read_question_permission_cannot_view_any_questions(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->viewAny($this->user));
        $this->assertFalse($this->policy->viewAny($this->otherUser));
    }

    #[Test]
    public function user_with_read_question_permission_can_view_any_questions(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::READ_QUESTION);
        $this->otherUser->givePermissionTo(PermissionDefaults::READ_QUESTION);

        // Act & Assert
        $this->assertTrue($this->policy->viewAny($this->user));
        $this->assertTrue($this->policy->viewAny($this->otherUser));
    }

    #[Test]
    public function user_without_read_question_permission_cannot_view_questions(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->view($this->user, $this->question));
        $this->assertFalse($this->policy->view($this->otherUser, $this->otherQuestion));
    }

    #[Test]
    public function user_with_read_question_permission_can_view_question_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_QUESTION);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->view($userWithPermission, $this->question));
    }

    #[Test]
    public function user_with_read_question_permission_cannot_view_question_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_QUESTION);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->otherQuestion));
    }

    #[Test]
    public function user_without_create_question_permission_cannot_create_questions(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->create($this->user));
        $this->assertFalse($this->policy->create($this->otherUser));
    }

    #[Test]
    public function user_with_create_question_permission_can_create_questions(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::CREATE_QUESTION);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->create($userWithPermission));
    }

    #[Test]
    public function user_without_update_question_permission_cannot_update_questions(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->update($this->user, $this->question));
        $this->assertFalse($this->policy->update($this->otherUser, $this->otherQuestion));
    }

    #[Test]
    public function user_with_update_question_permission_can_update_question_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_QUESTION);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->update($userWithPermission, $this->question));
    }

    #[Test]
    public function user_with_update_question_permission_cannot_update_question_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_QUESTION);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->otherQuestion));
    }

    #[Test]
    public function user_without_delete_question_permission_cannot_delete_questions(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->delete($this->user, $this->question));
        $this->assertFalse($this->policy->delete($this->otherUser, $this->otherQuestion));
    }

    #[Test]
    public function user_with_delete_question_permission_can_delete_question_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_QUESTION);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->delete($userWithPermission, $this->question));
    }

    #[Test]
    public function user_with_delete_question_permission_cannot_delete_question_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_QUESTION);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->otherQuestion));
    }

    #[Test]
    public function user_without_permissions_cannot_access_questions(): void
    {
        // Act & Assert - cannot access any question operations
        $this->assertFalse($this->policy->view($this->user, $this->question));
        $this->assertFalse($this->policy->create($this->user));
        $this->assertFalse($this->policy->update($this->user, $this->question));
        $this->assertFalse($this->policy->delete($this->user, $this->question));
    }

    #[Test]
    public function user_with_read_question_permission_but_no_active_financer_context_cannot_view_questions(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_QUESTION);

        // No context set for activeFinancerID
        Context::flush();

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->question));
    }

    #[Test]
    public function user_with_update_question_permission_but_no_active_financer_context_cannot_update_questions(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_QUESTION);

        // No context set for activeFinancerID
        Context::flush();

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->question));
    }

    #[Test]
    public function user_with_delete_question_permission_but_no_active_financer_context_cannot_delete_questions(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_QUESTION);

        // No context set for activeFinancerID
        Context::flush();

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->question));
    }

    #[Test]
    public function user_with_read_question_permission_and_wrong_financer_context_cannot_view_questions(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_QUESTION);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->question));
    }

    #[Test]
    public function user_with_update_question_permission_and_wrong_financer_context_cannot_update_questions(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_QUESTION);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->question));
    }

    #[Test]
    public function user_with_delete_question_permission_and_wrong_financer_context_cannot_delete_questions(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_QUESTION);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->question));
    }

    #[Test]
    public function user_with_delete_question_permission_can_restore_question(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);

        // Act
        $this->user->givePermissionTo(PermissionDefaults::DELETE_QUESTION);

        // Assert
        $this->assertTrue($this->policy->restore($this->user, $this->question));
    }

    #[Test]
    public function user_with_delete_question_permission_cannot_restore_other_users_question(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);

        // Act
        $this->user->givePermissionTo(PermissionDefaults::DELETE_QUESTION);

        // Assert
        $this->assertFalse($this->policy->restore($this->user, $this->otherQuestion));
    }

    #[Test]
    public function user_with_delete_question_permission_cannot_force_delete_question(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);

        // Act
        $this->user->givePermissionTo(PermissionDefaults::DELETE_QUESTION);

        // Assert
        $this->assertFalse($this->policy->forceDelete($this->user));
    }
}
