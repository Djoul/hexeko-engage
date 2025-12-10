<?php

namespace App\Livewire\AdminPanel;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class HomePage extends Component
{
    public string $activeTab = 'new-install';

    /** @var array<int, array{command: string, description: string}> */
    public array $commands = [
        [
            'command' => 'make quality-check',
            'description' => 'Vérification complète de la qualité du code',
            'usage' => 'Avant chaque commit',
        ],
        [
            'command' => 'make test',
            'description' => 'Lancer tous les tests',
            'usage' => 'Développement quotidien',
        ],
        [
            'command' => 'make reverb-status',
            'description' => 'Vérifier l\'état de Reverb',
            'usage' => 'Débogage WebSocket',
        ],
        [
            'command' => 'make docker-restart',
            'description' => 'Redémarrer tous les conteneurs',
            'usage' => 'En cas de problème',
        ],
    ];

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    #[Layout('admin-panel.layouts.livewire')]
    public function render(): View
    {
        return view('livewire.admin-panel.home-page');
    }
}
