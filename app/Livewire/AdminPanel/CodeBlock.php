<?php

namespace App\Livewire\AdminPanel;

use Illuminate\View\View;
use Livewire\Component;

class CodeBlock extends Component
{
    public string $code;

    public string $language;

    public string $copyButtonText = 'Copier';

    public function mount(string $code, string $language = 'bash'): void
    {
        $this->code = $code;
        $this->language = $language;
    }

    public function copyCode(): void
    {
        $this->copyButtonText = 'Copié !';

        $this->dispatch('code-copied', code: $this->code);

        // Reset button text after 2 seconds
        $this->dispatch('reset-copy-button');
    }

    public function resetCopyButton(): void
    {
        $this->copyButtonText = 'Copier';
    }

    public function render(): View
    {
        return view('livewire.admin-panel.code-block');
    }
}
