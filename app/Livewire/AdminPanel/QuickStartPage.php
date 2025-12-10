<?php

namespace App\Livewire\AdminPanel;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class QuickStartPage extends Component
{
    /** @var array<int, array{title: string, description: string, commands: array<int, string>}> */
    public array $steps = [
        'clone' => 'Cloner le projet',
        'setup' => 'Configuration initiale',
        'database' => 'Base de données',
        'services' => 'Démarrer les services',
        'verify' => 'Vérifier l\'installation',
    ];

    public string $activeStep = 'clone';

    public function setActiveStep(string $step): void
    {
        $this->activeStep = $step;
    }

    #[Layout('admin-panel.layouts.livewire')]
    public function render(): View
    {
        return view('livewire.admin-panel.quick-start-page');
    }
}
