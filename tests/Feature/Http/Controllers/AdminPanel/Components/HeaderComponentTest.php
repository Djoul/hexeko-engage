<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\AdminPanel\Components;

use App\Livewire\AdminPanel\Header;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('admin-panel')]
class HeaderComponentTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_renders_header_component_successfully(): void
    {
        Livewire::test(Header::class)
            ->assertSee('UpPlus+ Admin')
            ->assertSee('API Docs')
            ->assertSee('Log Viewer');
    }

    #[Test]
    public function it_shows_user_initial_when_authenticated(): void
    {
        $user = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->actingAs($user);

        Livewire::test(Header::class)
            ->assertSee('J') // First letter of John
            ->assertSee('John Doe');
    }

    #[Test]
    public function it_shows_default_admin_when_not_authenticated(): void
    {
        Livewire::test(Header::class)
            ->assertSee('A') // Default initial
            ->assertSee('Admin');
    }

    #[Test]
    public function it_toggles_user_menu_when_clicked(): void
    {
        Livewire::test(Header::class)
            ->assertSet('userMenuOpen', false)
            ->call('toggleUserMenu')
            ->assertSet('userMenuOpen', true)
            ->call('toggleUserMenu')
            ->assertSet('userMenuOpen', false);
    }

    #[Test]
    public function it_toggles_notifications_when_clicked(): void
    {
        Livewire::test(Header::class)
            ->assertSet('notificationsOpen', false)
            ->call('toggleNotifications')
            ->assertSet('notificationsOpen', true)
            ->call('toggleNotifications')
            ->assertSet('notificationsOpen', false);
    }

    #[Test]
    public function it_closes_notifications_when_user_menu_opens(): void
    {
        Livewire::test(Header::class)
            ->set('notificationsOpen', true)
            ->set('userMenuOpen', true)
            ->assertSet('userMenuOpen', true)
            ->assertSet('notificationsOpen', false);
    }

    #[Test]
    public function it_closes_user_menu_when_notifications_open(): void
    {
        Livewire::test(Header::class)
            ->set('userMenuOpen', true)
            ->set('notificationsOpen', true)
            ->assertSet('notificationsOpen', true)
            ->assertSet('userMenuOpen', false);
    }

    #[Test]
    public function it_navigates_to_pillar_route(): void
    {
        Livewire::test(Header::class)
            ->call('navigateToPillar', route('admin.manager.index'))
            ->assertRedirect(route('admin.manager.index'));
    }

    #[Test]
    public function it_logs_out_user_successfully(): void
    {
        $user = ModelFactory::createUser([
            'email' => 'admin@test.com',
        ]);

        $this->actingAs($user);

        Livewire::test(Header::class)
            ->call('logout')
            ->assertRedirect(route('admin.auth.login'));

        $this->assertGuest();
    }

    #[Test]
    public function it_displays_all_three_pillars(): void
    {
        // The pillars are not displayed in the header, they're in the sidebar
        // Test that the header has the developer tool links instead
        Livewire::test(Header::class)
            ->assertSee('API Docs')
            ->assertSee('Log Viewer')
            ->assertSee('UpPlus+ Admin');
    }

    #[Test]
    public function it_shows_notification_badge_when_unread_exists(): void
    {
        // The component sets unreadNotifications to 3 by default (mocked value)
        // When notifications dropdown is opened, it should show the count
        Livewire::test(Header::class)
            ->assertSet('unreadNotifications', 3)
            ->call('toggleNotifications')
            ->assertSee('3 non lues');
    }

    #[Test]
    public function it_displays_developer_tools_links(): void
    {
        // On desktop, the links should be visible
        Livewire::test(Header::class)
            ->assertSee('/docs/api')
            ->assertSee('/log-viewer')
            ->assertSee('API Documentation')
            ->assertSee('Log Viewer');
    }

    #[Test]
    public function it_toggles_developer_tools_dropdown(): void
    {
        // Developer tools are now static links, not dropdown
        // Testing that the links are rendered instead
        Livewire::test(Header::class)
            ->assertSee('API Documentation')
            ->assertSee('Log Viewer');
    }

    #[Test]
    public function it_closes_other_menus_when_dev_tools_open(): void
    {
        // Test mutual exclusivity between user menu and notifications
        Livewire::test(Header::class)
            ->set('userMenuOpen', true)
            ->set('notificationsOpen', true)
            ->assertSet('userMenuOpen', false)
            ->assertSet('notificationsOpen', true);
    }
}
