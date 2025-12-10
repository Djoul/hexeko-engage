<?php

declare(strict_types=1);

namespace App\Livewire\AdminPanel\Manager\Translation;

use App\Enums\Languages;
use App\Enums\OrigineInterfaces;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class TranslationListComponent extends Component
{
    use WithPagination;

    public string $search = '';

    public string $selectedInterface = '';

    public string $selectedLanguage = '';

    public string $filterStatus = 'all'; // all, translated, missing

    public bool $showBulkActions = false;

    public array $selectedKeys = [];

    public string $sortField = 'key';

    public string $sortDirection = 'asc';

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedInterface' => ['except' => ''],
        'selectedLanguage' => ['except' => ''],
        'filterStatus' => ['except' => 'all'],
        'sortField' => ['except' => 'key'],
        'sortDirection' => ['except' => 'asc'],
    ];

    protected $listeners = [
        'translationUpdated' => 'refreshList',
        'bulkActionCompleted' => 'resetSelection',
    ];

    public function mount(): void
    {
        // Set defaults from config or user preferences
        $this->selectedInterface = session('translation_interface', '');
        $this->selectedLanguage = session('translation_language', '');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedInterface(): void
    {
        session(['translation_interface' => $this->selectedInterface]);
        $this->resetPage();
    }

    public function updatedSelectedLanguage(): void
    {
        session(['translation_language' => $this->selectedLanguage]);
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function toggleKeySelection(int $keyId): void
    {
        if (in_array($keyId, $this->selectedKeys)) {
            $this->selectedKeys = array_filter($this->selectedKeys, fn ($id): bool => $id !== $keyId);
        } else {
            $this->selectedKeys[] = $keyId;
        }

        $this->showBulkActions = count($this->selectedKeys) > 0;
    }

    public function selectAll(): void
    {
        $keys = $this->getFilteredKeys()->pluck('id')->toArray();
        $this->selectedKeys = $keys;
        $this->showBulkActions = true;
    }

    public function deselectAll(): void
    {
        $this->selectedKeys = [];
        $this->showBulkActions = false;
    }

    public function bulkDelete(): void
    {
        if ($this->selectedKeys === []) {
            return;
        }

        // Confirm deletion
        $this->dispatch('confirm-bulk-delete', [
            'count' => count($this->selectedKeys),
            'keys' => $this->selectedKeys,
        ]);
    }

    public function bulkExport(): void
    {
        if ($this->selectedKeys === []) {
            return;
        }

        $this->dispatch('export-translations', [
            'keys' => $this->selectedKeys,
            'interface' => $this->selectedInterface,
            'language' => $this->selectedLanguage,
        ]);
    }

    public function editTranslation(int $keyId): void
    {
        $this->dispatch('edit-translation', ['keyId' => $keyId]);
    }

    public function duplicateKey(int $keyId): void
    {
        $key = TranslationKey::find($keyId);
        if ($key) {
            $this->dispatch('duplicate-translation-key', ['key' => $key->toArray()]);
        }
    }

    public function refreshList(): void
    {
        // Method called by event listeners to refresh the component
        $this->render();
    }

    public function resetSelection(): void
    {
        $this->selectedKeys = [];
        $this->showBulkActions = false;
    }

    private function getFilteredKeys(): Builder
    {
        $query = TranslationKey::query();

        // Apply search filter
        if (! empty($this->search)) {
            $query->where(function ($q): void {
                $q->where('key', 'like', '%'.$this->search.'%')
                    ->orWhereHas('translations', function ($q): void {
                        $q->where('value', 'like', '%'.$this->search.'%');
                    });
            });
        }

        // Apply interface filter
        if (! empty($this->selectedInterface)) {
            $query->whereHas('translations', function ($q): void {
                $q->where('interface_origin', $this->selectedInterface);
            });
        }

        // Apply language filter
        if (! empty($this->selectedLanguage)) {
            $query->whereHas('translations', function ($q): void {
                $q->where('language', $this->selectedLanguage);
            });
        }

        // Apply status filter
        if ($this->filterStatus === 'translated') {
            $query->whereHas('translations', function ($q): void {
                if (! empty($this->selectedInterface)) {
                    $q->where('interface_origin', $this->selectedInterface);
                }
                if (! empty($this->selectedLanguage)) {
                    $q->where('language', $this->selectedLanguage);
                }
            });
        } elseif ($this->filterStatus === 'missing') {
            $query->whereDoesntHave('translations', function ($q): void {
                if (! empty($this->selectedInterface)) {
                    $q->where('interface_origin', $this->selectedInterface);
                }
                if (! empty($this->selectedLanguage)) {
                    $q->where('language', $this->selectedLanguage);
                }
            });
        }

        // Apply sorting
        if ($this->sortField === 'key') {
            $query->orderBy('key', $this->sortDirection);
        } elseif ($this->sortField === 'created_at') {
            $query->orderBy('created_at', $this->sortDirection);
        } elseif ($this->sortField === 'updated_at') {
            $query->orderBy('updated_at', $this->sortDirection);
        }

        return $query;
    }

    public function render(): Factory|View
    {
        $keys = $this->getFilteredKeys()->paginate(20);

        // Get translation values for each key
        $keyIds = $keys->pluck('id');
        $translations = TranslationValue::whereIn('translation_key_id', $keyIds)
            ->when($this->selectedInterface, function ($q): void {
                $q->where('interface_origin', $this->selectedInterface);
            })
            ->when($this->selectedLanguage, function ($q): void {
                $q->where('language', $this->selectedLanguage);
            })
            ->get()
            ->groupBy('translation_key_id');

        // Calculate statistics
        $stats = [
            'total' => TranslationKey::count(),
            'filtered' => $this->getFilteredKeys()->count(),
            'selected' => count($this->selectedKeys),
        ];

        return view('livewire.admin-panel.manager.translation.list', [
            'keys' => $keys,
            'translations' => $translations,
            'interfaces' => OrigineInterfaces::cases(),
            'languages' => Languages::cases(),
            'stats' => $stats,
        ]);
    }
}
