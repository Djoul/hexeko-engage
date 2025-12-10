<?php

namespace App\Livewire\AdminPanel;

use Livewire\Component;

class SearchBar extends Component
{
    public string $query = '';

    public array $results = [];

    public function render(): string
    {
        return <<<'blade'
            <div>
                <input
                    type="search"
                    wire:model.live="query"
                    placeholder="Search admin-panel..."
                    class="w-full px-4 py-2 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
            </div>
        blade;
    }
}
