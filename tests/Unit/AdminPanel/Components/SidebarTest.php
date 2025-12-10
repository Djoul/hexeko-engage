<?php

declare(strict_types=1);

namespace Tests\Unit\AdminPanel\Components;

use App\Livewire\AdminPanel\Sidebar;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('admin-panel')]
class SidebarTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_renders_the_three_primary_navigation_pillars(): void
    {
        $user = $this->createGodUser();

        Livewire::actingAs($user)
            ->test(Sidebar::class)
            ->assertSuccessful()
            ->assertSet('navigationTree', function (array $tree): bool {
                $labels = array_map(static fn (array $node): string => (string) ($node['label'] ?? ''), $tree);

                return in_array('Dashboard', $labels, true)
                    && in_array('Manager', $labels, true)
                    && in_array('Documentation', $labels, true);
            })
            ->assertSeeHtml('data-nav-level="1"');
    }

    #[Test]
    public function it_marks_active_nodes_based_on_section_and_subsection(): void
    {
        $user = $this->createGodUser();

        Livewire::actingAs($user)
            ->test(Sidebar::class, [
                'activeSection' => 'manager',
                'activeSubsection' => 'translations',
            ])
            ->assertSet('navigationTree', function (array $tree): bool {
                foreach ($tree as $node) {
                    if (($node['id'] ?? null) !== 'manager') {
                        continue;
                    }

                    if (($node['is_active'] ?? false) !== true) {
                        return false;
                    }

                    foreach ($node['children'] as $child) {
                        if (($child['id'] ?? null) !== 'translations') {
                            continue;
                        }

                        return ($child['is_active'] ?? false) === true;
                    }
                }

                return false;
            });
    }

    #[Test]
    public function it_dispatches_events_when_section_changes(): void
    {
        $user = $this->createGodUser();

        Livewire::actingAs($user)
            ->test(Sidebar::class)
            ->call('changeSection', 'manager')
            ->assertSet('activeSection', 'manager')
            ->assertDispatched('navigation-changed', section: 'manager');
    }

    #[Test]
    public function it_emits_toggled_state_event_and_applies_modifier_class(): void
    {
        $user = $this->createGodUser();

        Livewire::actingAs($user)
            ->test(Sidebar::class)
            ->call('toggleCollapse')
            ->assertSet('isCollapsed', true)
            ->assertDispatched('sidebar-toggled', collapsed: true)
            ->assertSeeHtml('sidebar--collapsed');
    }

    #[Test]
    public function it_surfaces_breadcrumb_trail_from_navigation_builder(): void
    {
        $user = $this->createGodUser();

        Livewire::actingAs($user)
            ->test(Sidebar::class)
            ->assertSet('breadcrumbs', function (array $breadcrumbs): bool {
                return $breadcrumbs !== []
                    && ($breadcrumbs[0]['label'] ?? null) === 'Admin Panel'
                    && array_key_exists('route', $breadcrumbs[0]);
            });
    }

    private function createGodUser(): User
    {
        $user = ModelFactory::createUser([
            'email' => 'admin@test.com',
        ]);

        $team = ModelFactory::createTeam(['name' => 'Admin Team']);
        $user->setRelation('currentTeam', $team);

        if (! Role::where('name', 'GOD')->where('team_id', $team->id)->exists()) {
            ModelFactory::createRole(['name' => 'GOD', 'guard_name' => 'api', 'team_id' => $team->id]);
        }

        $user->assignRole('GOD');

        return $user;
    }
}
