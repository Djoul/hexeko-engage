<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\Survey\Models\Questionnaire;
use App\Integrations\Survey\Policies\QuestionnairePolicy;
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
#[Group('questionnaire')]
class QuestionnairePolicyTest extends TestCase
{
    use DatabaseTransactions;

    private QuestionnairePolicy $policy;

    private User $user;

    private User $otherUser;

    private Questionnaire $questionnaire;

    private Questionnaire $otherQuestionnaire;

    private Financer $financer;

    private Financer $otherFinancer;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new QuestionnairePolicy;

        // Create team for permissions
        $this->team = ModelFactory::createTeam();
        setPermissionsTeamId($this->team->id);

        // Create permissions
        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_QUESTIONNAIRE,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::CREATE_QUESTIONNAIRE,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::UPDATE_QUESTIONNAIRE,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::DELETE_QUESTIONNAIRE,
            'guard_name' => 'api',
        ]);

        // Create users
        $this->user = User::factory()->create(['team_id' => $this->team->id]);
        $this->otherUser = User::factory()->create(['team_id' => $this->team->id]);

        // Create financers
        $this->financer = Financer::factory()->create();
        $this->otherFinancer = Financer::factory()->create();

        // Create questionnaires
        $this->questionnaire = Questionnaire::factory()->create([
            'financer_id' => $this->financer->id,
            'name' => ['en' => 'Test Questionnaire', 'fr' => 'Questionnaire Test'],
            'description' => ['en' => 'Test Description', 'fr' => 'Description Test'],
            'instructions' => ['en' => 'Test Instructions', 'fr' => 'Instructions Test'],
        ]);

        $this->otherQuestionnaire = Questionnaire::factory()->create([
            'financer_id' => $this->otherFinancer->id,
            'name' => ['en' => 'Other Questionnaire', 'fr' => 'Autre Questionnaire'],
            'description' => ['en' => 'Other Description', 'fr' => 'Autre Description'],
            'instructions' => ['en' => 'Other Instructions', 'fr' => 'Autres Instructions'],
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
    public function user_without_read_questionnaire_permission_cannot_view_any_questionnaires(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->viewAny($this->user));
        $this->assertFalse($this->policy->viewAny($this->otherUser));
    }

    #[Test]
    public function user_with_read_questionnaire_permission_can_view_any_questionnaires(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::READ_QUESTIONNAIRE);
        $this->otherUser->givePermissionTo(PermissionDefaults::READ_QUESTIONNAIRE);

        // Act & Assert
        $this->assertTrue($this->policy->viewAny($this->user));
        $this->assertTrue($this->policy->viewAny($this->otherUser));
    }

    #[Test]
    public function user_without_read_questionnaire_permission_cannot_view_questionnaires(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->view($this->user, $this->questionnaire));
        $this->assertFalse($this->policy->view($this->otherUser, $this->otherQuestionnaire));
    }

    #[Test]
    public function user_with_read_questionnaire_permission_can_view_questionnaire_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_QUESTIONNAIRE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->view($userWithPermission, $this->questionnaire));
    }

    #[Test]
    public function user_with_read_questionnaire_permission_cannot_view_questionnaire_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_QUESTIONNAIRE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->otherQuestionnaire));
    }

    #[Test]
    public function user_without_create_questionnaire_permission_cannot_create_questionnaires(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->create($this->user));
        $this->assertFalse($this->policy->create($this->otherUser));
    }

    #[Test]
    public function user_with_create_questionnaire_permission_can_create_questionnaires(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::CREATE_QUESTIONNAIRE);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->create($userWithPermission));
    }

    #[Test]
    public function user_without_update_questionnaire_permission_cannot_update_questionnaires(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->update($this->user, $this->questionnaire));
        $this->assertFalse($this->policy->update($this->otherUser, $this->otherQuestionnaire));
    }

    #[Test]
    public function user_with_update_questionnaire_permission_can_update_questionnaire_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_QUESTIONNAIRE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->update($userWithPermission, $this->questionnaire));
    }

    #[Test]
    public function user_with_update_questionnaire_permission_cannot_update_questionnaire_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_QUESTIONNAIRE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->otherQuestionnaire));
    }

    #[Test]
    public function user_without_delete_questionnaire_permission_cannot_delete_questionnaires(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->delete($this->user, $this->questionnaire));
        $this->assertFalse($this->policy->delete($this->otherUser, $this->otherQuestionnaire));
    }

    #[Test]
    public function user_with_delete_questionnaire_permission_can_delete_questionnaire_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_QUESTIONNAIRE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->delete($userWithPermission, $this->questionnaire));
    }

    #[Test]
    public function user_with_delete_questionnaire_permission_cannot_delete_questionnaire_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_QUESTIONNAIRE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->otherQuestionnaire));
    }

    #[Test]
    public function user_without_permissions_cannot_access_questionnaires(): void
    {
        // Act & Assert - cannot access any questionnaire operations
        $this->assertFalse($this->policy->view($this->user, $this->questionnaire));
        $this->assertFalse($this->policy->create($this->user));
        $this->assertFalse($this->policy->update($this->user, $this->questionnaire));
        $this->assertFalse($this->policy->delete($this->user, $this->questionnaire));
    }

    #[Test]
    public function user_with_read_questionnaire_permission_but_no_active_financer_context_cannot_view_questionnaires(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_QUESTIONNAIRE);

        // No context set for activeFinancerID
        Context::flush();

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->questionnaire));
    }

    #[Test]
    public function user_with_update_questionnaire_permission_but_no_active_financer_context_cannot_update_questionnaires(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_QUESTIONNAIRE);

        // No context set for activeFinancerID
        Context::flush();

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->questionnaire));
    }

    #[Test]
    public function user_with_delete_questionnaire_permission_but_no_active_financer_context_cannot_delete_questionnaires(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_QUESTIONNAIRE);

        // No context set for activeFinancerID
        Context::flush();

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->questionnaire));
    }

    #[Test]
    public function user_with_read_questionnaire_permission_and_wrong_financer_context_cannot_view_questionnaires(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_QUESTIONNAIRE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->questionnaire));
    }

    #[Test]
    public function user_with_update_questionnaire_permission_and_wrong_financer_context_cannot_update_questionnaires(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_QUESTIONNAIRE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->questionnaire));
    }

    #[Test]
    public function user_with_delete_questionnaire_permission_and_wrong_financer_context_cannot_delete_questionnaires(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_QUESTIONNAIRE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->questionnaire));
    }

    #[Test]
    public function user_with_delete_questionnaire_permission_can_restore_questionnaire(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);

        // Act
        $this->user->givePermissionTo(PermissionDefaults::DELETE_QUESTIONNAIRE);

        // Assert
        $this->assertTrue($this->policy->restore($this->user, $this->questionnaire));
    }

    #[Test]
    public function user_with_delete_questionnaire_permission_cannot_restore_other_users_questionnaire(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);

        // Act
        $this->user->givePermissionTo(PermissionDefaults::DELETE_QUESTIONNAIRE);

        // Assert
        $this->assertFalse($this->policy->restore($this->user, $this->otherQuestionnaire));
    }

    #[Test]
    public function user_with_delete_questionnaire_permission_cannot_force_delete_questionnaire(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);

        // Act
        $this->user->givePermissionTo(PermissionDefaults::DELETE_QUESTIONNAIRE);

        // Assert
        $this->assertFalse($this->policy->forceDelete($this->user));
    }
}
