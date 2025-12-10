<?php

declare(strict_types=1);

namespace App\Livewire\AdminPanel\Navigation;

use App\Livewire\AdminPanel\Sidebar;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Name;
use Livewire\Component;

#[Name('admin-panel.navigation.menu-level-2')]
class MenuLevel2 extends Component
{
    public array $node = [];

    public bool $isCollapsed = false;

    public function navigate(): void
    {
        $pillar = (string) ($this->node['pillar'] ?? '');
        $section = (string) ($this->node['id'] ?? '');

        if ($pillar === '' || $section === '') {
            return;
        }

        $this->dispatch(
            'sidebar-navigate',
            action: 'section',
            pillar: $pillar,
            section: $section
        )->to(Sidebar::class);
    }

    public function render(): View
    {
        return view('livewire.admin-panel.navigation.menu-level-2');
    }
}
