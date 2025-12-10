<?php

namespace App\Livewire\AdminPanel;

use Livewire\Attributes\Layout;
use Livewire\Component;

class ApiEndpointTester extends Component
{
    public string $endpoint = '';

    public string $method = 'GET';

    public string $body = '';

    public string $response = '';

    #[Layout('admin-panel.layouts.livewire')]
    public function render(): string
    {
        return <<<'blade'
            <div>
                <h2 class="text-lg font-semibold mb-4">API Endpoint Tester</h2>
                <p>This is a placeholder for the API endpoint tester component.</p>
            </div>
        blade;
    }
}
