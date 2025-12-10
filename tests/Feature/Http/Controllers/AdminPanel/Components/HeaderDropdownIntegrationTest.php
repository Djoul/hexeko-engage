<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\AdminPanel\Components;

use App\Livewire\AdminPanel\Header;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('admin-panel')]
class HeaderDropdownIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_handles_rapid_dropdown_toggling(): void
    {
        $component = Livewire::test(Header::class);

        // Rapidly toggle between dropdowns
        $component->call('toggleUserMenu')
            ->assertSet('userMenuOpen', true)
            ->call('toggleUserMenu')
            ->assertSet('userMenuOpen', false)
            ->call('toggleNotifications')
            ->assertSet('notificationsOpen', true)
            ->call('toggleNotifications')
            ->assertSet('notificationsOpen', false);

        // Ensure all are closed
        $component->assertSet('userMenuOpen', false)
            ->assertSet('notificationsOpen', false)
            ->assertSet('devToolsOpen', false);
    }

    #[Test]
    public function it_maintains_exclusivity_between_all_dropdowns(): void
    {
        $component = Livewire::test(Header::class);

        // Open user menu
        $component->set('userMenuOpen', true)
            ->assertSet('userMenuOpen', true)
            ->assertSet('notificationsOpen', false)
            ->assertSet('devToolsOpen', false);

        // Open notifications - should close user menu
        $component->set('notificationsOpen', true)
            ->assertSet('userMenuOpen', false)
            ->assertSet('notificationsOpen', true)
            ->assertSet('devToolsOpen', false);

        // Open user menu - should close notifications
        $component->set('userMenuOpen', true)
            ->assertSet('userMenuOpen', true)
            ->assertSet('notificationsOpen', false);
    }

    #[Test]
    public function it_syncs_alpine_state_with_livewire_properties(): void
    {
        // This tests the @entangle directive synchronization
        $component = Livewire::test(Header::class);

        // Simulate Alpine.js updating the property
        $component->set('userMenuOpen', true)
            ->assertSet('userMenuOpen', true);

        // When Alpine sets it to false (via click-away), Livewire should sync
        $component->set('userMenuOpen', false)
            ->assertSet('userMenuOpen', false);

        // Test with all dropdowns
        $component->set('notificationsOpen', true)
            ->assertSet('notificationsOpen', true)
            ->set('notificationsOpen', false)
            ->assertSet('notificationsOpen', false);
    }

    #[Test]
    public function it_handles_sequential_dropdown_opening(): void
    {
        $component = Livewire::test(Header::class);

        // Open each dropdown in sequence
        $component->call('toggleUserMenu')
            ->assertSet('userMenuOpen', true);

        $component->call('toggleNotifications')
            ->assertSet('notificationsOpen', true);
        // userMenuOpen is automatically set to false by updatedNotificationsOpen

        // Close notifications and open user menu
        $component->call('toggleNotifications')  // Close notifications
            ->assertSet('notificationsOpen', false)
            ->call('toggleUserMenu')  // Then open user menu
            ->assertSet('userMenuOpen', true);
    }
}
