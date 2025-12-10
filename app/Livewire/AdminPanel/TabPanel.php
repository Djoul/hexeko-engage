<?php

namespace App\Livewire\AdminPanel;

use Illuminate\View\View;
use Livewire\Component;

class TabPanel extends Component
{
    public string $activeTab = 'new-install';

    public function mount(string $activeTab = 'new-install'): void
    {
        $this->activeTab = $activeTab;
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->dispatch('tab-changed', tab: $tab)->to(HomePage::class);
    }

    public function render(): View
    {
        return view('livewire.admin-panel.tab-panel');
    }
}
