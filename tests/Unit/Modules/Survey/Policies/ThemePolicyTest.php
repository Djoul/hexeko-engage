<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\Survey\Models\Theme;
use App\Integrations\Survey\Policies\ThemePolicy;
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
#[Group('theme')]
class ThemePolicyTest extends TestCase
{
    use DatabaseTransactions;

    private ThemePolicy $policy;

    private User $user;

    private User $otherUser;

    private Theme $theme;

    private Theme $otherTheme;

    private Financer $financer;

    private Financer $otherFinancer;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ThemePolicy;

        // Create team for permissions
        $this->team = ModelFactory::createTeam();
        setPermissionsTeamId($this->team->id);

        // Create permissions
        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_THEME,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::CREATE_THEME,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::UPDATE_THEME,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::DELETE_THEME,
            'guard_name' => 'api',
        ]);

        // Create users
        $this->user = User::factory()->create(['team_id' => $this->team->id]);
        $this->otherUser = User::factory()->create(['team_id' => $this->team->id]);

        // Create financers
        $this->financer = Financer::factory()->create();
        $this->otherFinancer = Financer::factory()->create();

        // Create themes
        $this->theme = Theme::factory()->create([
            'financer_id' => $this->financer->id,
            'name' => ['en' => 'Test Theme', 'fr' => 'Thème Test'],
            'description' => ['en' => 'Test Description', 'fr' => 'Description Test'],
        ]);

        $this->otherTheme = Theme::factory()->create([
            'financer_id' => $this->otherFinancer->id,
            'name' => ['en' => 'Other Theme', 'fr' => 'Autre Thème'],
            'description' => ['en' => 'Other Description', 'fr' => 'Autre Description'],
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
    public function user_without_read_theme_permission_cannot_view_any_themes(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->viewAny($this->user));
        $this->assertFalse($this->policy->viewAny($this->otherUser));
    }

    #[Test]
    public function user_with_read_theme_permission_can_view_any_themes(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::READ_THEME);
        $this->otherUser->givePermissionTo(PermissionDefaults::READ_THEME);

        // Act & Assert
        $this->assertTrue($this->policy->viewAny($this->user));
        $this->assertTrue($this->policy->viewAny($this->otherUser));
    }

    #[Test]
    public function user_without_read_theme_permission_cannot_view_themes(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->view($this->user, $this->theme));
        $this->assertFalse($this->policy->view($this->otherUser, $this->otherTheme));
    }

    #[Test]
    public function user_with_read_theme_permission_can_view_theme_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_THEME);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->view($userWithPermission, $this->theme));
    }

    #[Test]
    public function user_with_read_theme_permission_cannot_view_theme_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_THEME);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->otherTheme));
    }

    #[Test]
    public function user_without_create_theme_permission_cannot_create_themes(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->create($this->user));
        $this->assertFalse($this->policy->create($this->otherUser));
    }

    #[Test]
    public function user_with_create_theme_permission_can_create_themes(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create();
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);
        $userWithPermission->givePermissionTo(PermissionDefaults::CREATE_THEME);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->create($userWithPermission));
    }

    #[Test]
    public function user_without_update_theme_permission_cannot_update_themes(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->update($this->user, $this->theme));
        $this->assertFalse($this->policy->update($this->otherUser, $this->otherTheme));
    }

    #[Test]
    public function user_with_update_theme_permission_can_update_theme_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_THEME);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->update($userWithPermission, $this->theme));
    }

    #[Test]
    public function user_with_update_theme_permission_cannot_update_theme_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_THEME);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->otherTheme));
    }

    #[Test]
    public function user_without_delete_theme_permission_cannot_delete_themes(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->delete($this->user, $this->theme));
        $this->assertFalse($this->policy->delete($this->otherUser, $this->otherTheme));
    }

    #[Test]
    public function user_with_delete_theme_permission_can_delete_theme_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_THEME);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->delete($userWithPermission, $this->theme));
    }

    #[Test]
    public function user_with_delete_theme_permission_cannot_delete_theme_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_THEME);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->otherTheme));
    }

    #[Test]
    public function user_without_permissions_cannot_access_themes(): void
    {
        // Act & Assert - cannot access any theme operations
        $this->assertFalse($this->policy->view($this->user, $this->theme));
        $this->assertFalse($this->policy->create($this->user));
        $this->assertFalse($this->policy->update($this->user, $this->theme));
        $this->assertFalse($this->policy->delete($this->user, $this->theme));
    }

    #[Test]
    public function user_with_read_theme_permission_but_no_active_financer_context_cannot_view_themes(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_THEME);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // No context set for activeFinancerID

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->theme));
    }

    #[Test]
    public function user_with_update_theme_permission_but_no_active_financer_context_cannot_update_themes(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_THEME);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // No context set for activeFinancerID

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->theme));
    }

    #[Test]
    public function user_with_delete_theme_permission_but_no_active_financer_context_cannot_delete_themes(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_THEME);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // No context set for activeFinancerID

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->theme));
    }

    #[Test]
    public function user_with_read_theme_permission_and_wrong_financer_context_cannot_view_themes(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_THEME);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->theme));
    }

    #[Test]
    public function user_with_update_theme_permission_and_wrong_financer_context_cannot_update_themes(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_THEME);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->theme));
    }

    #[Test]
    public function user_with_delete_theme_permission_and_wrong_financer_context_cannot_delete_themes(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_THEME);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->theme));
    }

    #[Test]
    public function user_with_delete_theme_permission_can_restore_theme(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::DELETE_THEME);
        $this->user->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Assert
        $this->assertTrue($this->policy->restore($this->user, $this->theme));
    }

    #[Test]
    public function user_with_delete_theme_permission_cannot_restore_other_users_theme(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::DELETE_THEME);
        $this->user->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for activeFinancerID
        Context::add('financer_id', $this->financer->id);

        // Assert
        $this->assertFalse($this->policy->restore($this->user, $this->otherTheme));
    }

    #[Test]
    public function user_with_delete_theme_permission_cannot_force_delete_theme(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);

        // Act
        $this->user->givePermissionTo(PermissionDefaults::DELETE_THEME);

        // Assert
        $this->assertFalse($this->policy->forceDelete($this->user));
    }
}
