<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\Survey\Models\Survey;
use App\Integrations\Survey\Policies\SurveyPolicy;
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
class SurveyPolicyTest extends TestCase
{
    use DatabaseTransactions;

    private SurveyPolicy $policy;

    private User $user;

    private User $otherUser;

    private Survey $survey;

    private Survey $otherSurvey;

    private Financer $financer;

    private Financer $otherFinancer;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new SurveyPolicy;

        // Create team for permissions
        $this->team = ModelFactory::createTeam();
        setPermissionsTeamId($this->team->id);

        // Create permissions
        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_SURVEY,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::CREATE_SURVEY,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::UPDATE_SURVEY,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::DELETE_SURVEY,
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
    public function user_without_read_survey_permission_cannot_view_any_surveys(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->viewAny($this->user));
        $this->assertFalse($this->policy->viewAny($this->otherUser));
    }

    #[Test]
    public function user_with_read_survey_permission_can_view_any_surveys(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::READ_SURVEY);
        $this->otherUser->givePermissionTo(PermissionDefaults::READ_SURVEY);

        // Act & Assert
        $this->assertTrue($this->policy->viewAny($this->user));
        $this->assertTrue($this->policy->viewAny($this->otherUser));
    }

    #[Test]
    public function user_without_read_survey_permission_cannot_view_surveys(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->view($this->user, $this->survey));
        $this->assertFalse($this->policy->view($this->otherUser, $this->otherSurvey));
    }

    #[Test]
    public function user_with_read_survey_permission_can_view_survey_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_SURVEY);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->view($userWithPermission, $this->survey));
    }

    #[Test]
    public function user_with_read_survey_permission_cannot_view_survey_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_SURVEY);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->otherSurvey));
    }

    #[Test]
    public function user_without_create_survey_permission_cannot_create_surveys(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->create($this->user));
        $this->assertFalse($this->policy->create($this->otherUser));
    }

    #[Test]
    public function user_with_create_survey_permission_can_create_surveys(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create();
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);
        $userWithPermission->givePermissionTo(PermissionDefaults::CREATE_SURVEY);

        // Act & Assert
        $this->assertTrue($this->policy->create($userWithPermission));
    }

    #[Test]
    public function user_without_update_survey_permission_cannot_update_surveys(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->update($this->user, $this->survey));
        $this->assertFalse($this->policy->update($this->otherUser, $this->otherSurvey));
    }

    #[Test]
    public function user_with_update_survey_permission_can_update_survey_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_SURVEY);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->update($userWithPermission, $this->survey));
    }

    #[Test]
    public function user_with_update_survey_permission_cannot_update_survey_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_SURVEY);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->otherSurvey));
    }

    #[Test]
    public function user_without_delete_survey_permission_cannot_delete_surveys(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->delete($this->user, $this->survey));
        $this->assertFalse($this->policy->delete($this->otherUser, $this->otherSurvey));
    }

    #[Test]
    public function user_with_delete_survey_permission_can_delete_survey_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_SURVEY);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->delete($userWithPermission, $this->survey));
    }

    #[Test]
    public function user_with_delete_survey_permission_cannot_delete_survey_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_SURVEY);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->otherSurvey));
    }

    #[Test]
    public function user_without_permissions_cannot_access_surveys(): void
    {
        // Act & Assert - cannot access any survey operations
        $this->assertFalse($this->policy->view($this->user, $this->survey));
        $this->assertFalse($this->policy->create($this->user));
        $this->assertFalse($this->policy->update($this->user, $this->survey));
        $this->assertFalse($this->policy->delete($this->user, $this->survey));
    }

    #[Test]
    public function user_with_read_survey_permission_but_no_active_financer_context_cannot_view_surveys(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_SURVEY);

        // No context set for activeFinancerID
        Context::flush();

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->survey));
    }

    #[Test]
    public function user_with_update_survey_permission_but_no_active_financer_context_cannot_update_surveys(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_SURVEY);

        // No context set for activeFinancerID
        Context::flush();

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->survey));
    }

    #[Test]
    public function user_with_delete_survey_permission_but_no_active_financer_context_cannot_delete_surveys(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_SURVEY);

        // No context set for activeFinancerID
        Context::flush();

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->survey));
    }

    #[Test]
    public function user_with_read_survey_permission_and_wrong_financer_context_cannot_view_surveys(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_SURVEY);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->survey));
    }

    #[Test]
    public function user_with_update_survey_permission_and_wrong_financer_context_cannot_update_surveys(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_SURVEY);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->survey));
    }

    #[Test]
    public function user_with_delete_survey_permission_and_wrong_financer_context_cannot_delete_surveys(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_SURVEY);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->survey));
    }

    #[Test]
    public function user_with_delete_survey_permission_can_restore_survey(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);

        // Act
        $this->user->givePermissionTo(PermissionDefaults::DELETE_SURVEY);

        // Assert
        $this->assertTrue($this->policy->restore($this->user, $this->survey));
    }

    #[Test]
    public function user_with_delete_survey_permission_cannot_restore_other_users_survey(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);

        // Act
        $this->user->givePermissionTo(PermissionDefaults::DELETE_SURVEY);

        // Assert
        $this->assertFalse($this->policy->restore($this->user, $this->otherSurvey));
    }

    #[Test]
    public function user_with_delete_survey_permission_cannot_force_delete_survey(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);

        // Act
        $this->user->givePermissionTo(PermissionDefaults::DELETE_SURVEY);

        // Assert
        $this->assertFalse($this->policy->forceDelete($this->user));
    }
}
