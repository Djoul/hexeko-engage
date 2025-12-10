<?php

declare(strict_types=1);

namespace App\Livewire\AdminPanel\Navigation;

use App\Livewire\AdminPanel\Sidebar;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Name;
use Livewire\Component;

#[Name('admin-panel.navigation.menu-level-3')]
class MenuLevel3 extends Component
{
    public array $node = [];

    public function navigate(): void
    {
        $pillar = (string) ($this->node['pillar'] ?? '');
        $section = (string) ($this->node['section'] ?? '');
        $subsection = $this->node['subsection'] ?? null;

        if ($pillar === '' || $section === '') {
            return;
        }

        $this->dispatch(
            'sidebar-navigate',
            action: 'leaf',
            pillar: $pillar,
            section: $section,
            subsection: is_string($subsection) ? $subsection : null
        )->to(Sidebar::class);
    }

    public function render(): View
    {
        return view('livewire.admin-panel.navigation.menu-level-3');
    }
}
