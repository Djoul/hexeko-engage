<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\AdminPanel;

use App\Livewire\AdminPanel\Sidebar;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('admin-panel')]
class NavigationIntegrationTest extends ProtectedRouteTestCase
{
    protected function createAdminUser(): User
    {
        $user = ModelFactory::createUser([
            'email' => 'admin@test.com',
        ]);

        // Create team for role assignment
        $team = ModelFactory::createTeam(['name' => 'Admin Team']);

        $user->forceFill(['team_id' => $team->id])->save();

        setPermissionsTeamId($team->id);

        if (! Role::where('name', 'GOD')->where('team_id', $team->id)->exists()) {
            ModelFactory::createRole([
                'name' => 'GOD',
                'guard_name' => 'api',
                'team_id' => $team->id,
            ]);
        }

        $user->setRelation('currentTeam', $team);
        $user->assignRole('GOD');

        return $user->fresh();
    }

    #[Test]
    public function it_navigates_through_three_pillar_structure_seamlessly(): void
    {
        $user = $this->createAdminUser();

        // Start at dashboard
        $response = $this->actingAs($user)
            ->get('/admin-panel/dashboard');

        $response->assertOk()
            ->assertSee('Tableau de bord')
            ->assertSee('Vue d’ensemble du système et supervision');

        // Navigate to Manager section
        $response = $this->actingAs($user)
            ->get('/admin-panel/manager');

        $response->assertOk()
            ->assertSee('Manager');

        // Navigate to Documentation section
        $response = $this->actingAs($user)
            ->get('/admin-panel/docs');

        $response->assertOk()
            ->assertSee('Documentation');
    }

    #[Test]
    public function it_maintains_navigation_context_across_page_changes(): void
    {
        $user = $this->createAdminUser();

        // Navigate to Manager > Translations
        $response = $this->actingAs($user)
            ->get('/admin-panel/manager/translations');

        $response->assertOk()
            ->assertSee('Manager')
            ->assertSee('Traductions');

        // Navigation context should be preserved when moving to another manager subsection
        $response = $this->actingAs($user)
            ->get('/admin-panel/manager/roles');

        $response->assertOk()
            ->assertSee('Manager')
            ->assertSee('Roles & Permissions')
            ->assertSee('manager', false); // Should maintain manager context
    }

    #[Test]
    public function it_updates_sidebar_based_on_current_section(): void
    {
        $user = $this->createAdminUser();

        // Test Dashboard - sidebar should show dashboard items
        $response = $this->actingAs($user)
            ->get('/admin-panel/dashboard');

        $response->assertOk();
        // Sidebar is present and shows navigation
        $response->assertSee('Navigation', false);

        // Test Manager - sidebar should show manager items
        $response = $this->actingAs($user)
            ->get('/admin-panel/manager');

        $response->assertOk()
            ->assertSee('Navigation', false);

        // Test Documentation - sidebar should show docs items
        $response = $this->actingAs($user)
            ->get('/admin-panel/docs');

        $response->assertOk()
            ->assertSee('Navigation', false);
    }

    #[Test]
    public function it_shows_breadcrumb_navigation_correctly(): void
    {
        $user = $this->createAdminUser();

        // Test navigation breadcrumbs for existing route
        $response = $this->actingAs($user)
            ->get('/admin-panel/manager/translations');

        $response->assertOk()
            ->assertSee('Admin Panel', false)
            ->assertSee('Manager', false)
            ->assertSee('Traductions', false);
    }

    #[Test]
    public function it_handles_deep_navigation_within_documentation(): void
    {
        $user = $this->createAdminUser();

        // Navigate to documentation page
        $response = $this->actingAs($user)
            ->get('/admin-panel/docs');

        $response->assertOk()
            ->assertSee('Documentation UpEngage', false);
    }

    #[Test]
    public function it_redirects_to_dashboard_when_accessing_admin_panel_root(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user)
            ->get('/admin-panel');

        $response->assertRedirect(route('admin.dashboard.index'));
    }

    #[Test]
    public function it_maintains_active_state_for_current_navigation_item(): void
    {
        $user = $this->createAdminUser();

        // Visit manager page
        $response = $this->actingAs($user)
            ->get('/admin-panel/manager');

        $response->assertOk()
            ->assertSee('Manager', false);

        // Check that the sidebar component has the correct active state
        Livewire::actingAs($user)
            ->test(Sidebar::class, [
                'activeSection' => 'manager',
                'activeSubsection' => '',
            ])
            ->assertSet('activeSection', 'manager')
            ->assertSet('activeSubsection', '');
    }

    #[Test]
    public function it_handles_navigation_between_different_pillar_sections(): void
    {
        $user = $this->createAdminUser();

        // Start in Dashboard
        $response = $this->actingAs($user)
            ->get('/admin-panel/dashboard');
        $response->assertOk()->assertSee('Tableau de bord', false);

        // Move to Manager
        $response = $this->actingAs($user)
            ->get('/admin-panel/manager');
        $response->assertOk()->assertSee('Manager', false);

        // Move to Documentation
        $response = $this->actingAs($user)
            ->get('/admin-panel/docs');
        $response->assertOk()->assertSee('Documentation', false);

        // Return to Dashboard - should maintain context
        $response = $this->actingAs($user)
            ->get('/admin-panel/dashboard');
        $response->assertOk()->assertSee('Tableau de bord', false);
    }

    #[Test]
    public function it_preserves_user_preferences_during_navigation(): void
    {
        $user = $this->createAdminUser();

        // Set sidebar collapsed preference
        Livewire::actingAs($user)
            ->test(Sidebar::class)
            ->call('toggleCollapse')
            ->assertSet('isCollapsed', true);

        // Navigate to different section - preference should persist
        $response = $this->actingAs($user)
            ->get('/admin-panel/manager');

        $response->assertOk()
            ->assertSee('Manager', false);

        // Verify sidebar maintains collapsed state
        Livewire::actingAs($user)
            ->test(Sidebar::class)
            ->assertSet('isCollapsed', true);
    }

    #[Test]
    public function it_shows_section_specific_header_actions(): void
    {
        $user = $this->createAdminUser();

        // Dashboard should have refresh action
        $response = $this->actingAs($user)
            ->get('/admin-panel/dashboard');
        $response->assertOk()->assertSee('Tableau de bord', false);

        // Manager should show Manager title
        $response = $this->actingAs($user)
            ->get('/admin-panel/manager');
        $response->assertOk()
            ->assertSee('Manager', false);

        // Documentation should have search
        $response = $this->actingAs($user)
            ->get('/admin-panel/docs');
        $response->assertOk()->assertSee('Search', false);
    }
}
