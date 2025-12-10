<?php

declare(strict_types=1);

namespace App\Livewire\AdminPanel;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class UnderConstruction extends Component
{
    public string $title = 'Page Under Construction';

    public string $description = 'This feature is currently being developed.';

    public string $icon = 'heroicon-o-wrench-screwdriver';

    public ?string $expectedDate = null;

    public array $features = [];

    public function mount(
        ?string $title = null,
        ?string $description = null,
        ?string $icon = null,
        ?string $expectedDate = null,
        array $features = []
    ): void {
        if (! in_array($title, [null, '', '0'], true)) {
            $this->title = $title;
        }
        if (! in_array($description, [null, '', '0'], true)) {
            $this->description = $description;
        }
        if (! in_array($icon, [null, '', '0'], true)) {
            $this->icon = $icon;
        }
        if (! in_array($expectedDate, [null, '', '0'], true)) {
            $this->expectedDate = $expectedDate;
        }
        if ($features !== []) {
            $this->features = $features;
        }
    }

    public function render(): Factory|View
    {
        return view('livewire.admin-panel.under-construction');
    }
}
