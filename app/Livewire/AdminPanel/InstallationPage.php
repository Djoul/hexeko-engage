<?php

namespace App\Livewire\AdminPanel;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class InstallationPage extends Component
{
    /** @var array<string, string> */
    public array $tabs = [
        'prerequisites' => 'Prérequis',
        'docker' => 'Installation Docker',
        'environment' => 'Variables d\'environnement',
        'containers' => 'Gestion des conteneurs',
    ];

    public string $activeTab = 'prerequisites';

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    #[Layout('admin-panel.layouts.livewire')]
    public function render(): View
    {
        return view('livewire.admin-panel.installation-page');
    }
}
