<?php

namespace App\Livewire\AdminPanel;

use Livewire\Component;

class WebSocketDemo extends Component
{
    public string $reverbKey = '';

    public function mount(string $reverbKey = ''): void
    {
        $this->reverbKey = $reverbKey;
    }

    public function render(): string
    {
        return <<<'blade'
            <div>
                <h2 class="text-lg font-semibold mb-4">WebSocket Demo Component</h2>
                <p>This is a placeholder for the WebSocket demo component.</p>
                <p>Reverb Key: {{ $reverbKey }}</p>
            </div>
        blade;
    }
}
