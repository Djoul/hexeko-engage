<?php

declare(strict_types=1);

namespace App\Livewire\AdminPanel;

use App\Services\AdminPanel\NavigationBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Header extends Component
{
    public bool $userMenuOpen = false;

    public bool $notificationsOpen = false;

    public int $unreadNotifications = 0;

    public array $pillars = [];

    public ?string $userName = null;

    public ?string $userInitial = null;

    public function mount(): void
    {
        // Get user information
        $user = Auth::user();
        if ($user) {
            // Build full name from first_name and last_name
            $firstName = $user->first_name ?? '';
            $lastName = $user->last_name ?? '';
            $this->userName = in_array(trim($firstName.' '.$lastName), ['', '0'], true) ? 'Admin' : trim($firstName.' '.$lastName);
            $this->userInitial = substr($firstName ?: 'A', 0, 1);
        } else {
            $this->userName = 'Admin';
            $this->userInitial = 'A';
        }

        // Build navigation
        $navigationBuilder = app(NavigationBuilder::class);
        $navigation = $navigationBuilder->build(
            request()->segment(2) ?? 'dashboard',
            request()->segment(3) ?? null,
            request()->route()?->getName()
        );
        $this->pillars = $navigation['pillars'] ?? [];

        // Mock notification count - replace with real data
        $this->unreadNotifications = 3;
    }

    public function updatedUserMenuOpen(bool $value): void
    {
        if ($value) {
            $this->notificationsOpen = false;
        }
    }

    public function updatedNotificationsOpen(bool $value): void
    {
        if ($value) {
            $this->userMenuOpen = false;
        }
    }

    public function toggleUserMenu(): void
    {
        $this->userMenuOpen = ! $this->userMenuOpen;
        if ($this->userMenuOpen) {
            $this->notificationsOpen = false;
        }
    }

    public function toggleNotifications(): void
    {
        $this->notificationsOpen = ! $this->notificationsOpen;
        if ($this->notificationsOpen) {
            $this->userMenuOpen = false;
        }
    }

    public function logout(): void
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(route('admin.auth.login'));
    }

    public function navigateToPillar(string $route): void
    {
        $this->redirect($route);
    }

    public function render(): View
    {
        return view('livewire.admin-panel.header');
    }
}
