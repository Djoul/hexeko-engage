<?php

declare(strict_types=1);

namespace App\Livewire\AdminPanel\Manager\Translation;

use App\Actions\Translation\ExportTranslationsAction;
use App\Actions\Translation\ImportTranslationsAction;
use App\DTOs\Translation\ImportTranslationDTO;
use App\Enums\Languages;
use App\Enums\OrigineInterfaces;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use App\Services\EnvironmentService;
use App\Services\Models\TranslationKeyService;
use App\Services\Models\TranslationValueService;
use App\Services\TranslationMigrations\S3StorageService;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TranslationManager extends Component
{
    use WithFileUploads, WithPagination;

    public string $selectedInterface = 'web_financer';

    /** @var array<int, string> */
    public array $selectedLocales = [];

    public string $search = '';

    public bool $showImportModal = false;

    public bool $showExportModal = false;

    public bool $showExportToS3Modal = false;

    public bool $showAddKeyModal = false;

    public bool $showPreview = false;

    // Export to S3 modal properties
    /** @var array<string, bool> */
    public array $selectedInterfacesForExport = [];

    // Import related
    /** @var TemporaryUploadedFile|null */
    public $importFile;

    /** @var array<string, mixed> */
    public array $previewData = [];

    public string $importType = 'multilingual';

    public string $importInterface = 'web_financer';

    public ?string $importFileContent = null;

    public ?string $importFileName = null;

    public bool $updateExistingValues = false;

    // Add key form
    public string $newKey = '';

    /** @var array<string, string> */
    public array $newValues = [];

    // Edit mode
    /** @var array<string, string> */
    public array $editingValues = [];

    // Edit drawer
    public bool $showEditDrawer = false;

    public ?TranslationKey $editingKey = null;

    public bool $showAllSecondaryLanguages = false;

    public bool $showAllSecondaryLanguagesForNew = false;

    public bool $hasPendingExport = false;

    public ?string $pendingChangeSource = null;

    private EnvironmentService $environmentService;

    /** @var array<string, string> */
    protected $listeners = [
        'migration-applied' => 'handleMigrationApplied',
    ];

    /** @var array<string, array<string, mixed>> */
    protected $queryString = [
        'selectedInterface' => ['except' => OrigineInterfaces::WEB_FINANCER],
        'selectedLocales' => ['except' => []],
        'search' => ['except' => ''],
    ];

    /** @var array<string, string> */
    protected $rules = [
        'importFile' => 'required|file|mimes:json|max:10240',
        'importType' => 'required|in:multilingual,single',
        'updateExistingValues' => 'boolean',
        'newKey' => 'required|string|max:255',
        'newValues.*' => 'nullable|string',
    ];

    public function boot(EnvironmentService $environmentService): void
    {
        $this->environmentService = $environmentService;
    }

    public function mount(): void
    {
        // Update the importInterface rule with enum values
        $this->rules['importInterface'] = 'required|in:'.implode(',', OrigineInterfaces::getValues());
        if ($this->selectedLocales === []) {
            $this->selectedLocales = [Languages::FRENCH, Languages::PORTUGUESE];
        }

        $this->initializeNewValues();
        $this->refreshPendingExportState();
    }

    public function render(): View
    {
        return view('livewire.admin-panel.manager.translation.manager', [
            'translations' => $this->getTranslations(),
            'availableLocales' => $this->getAvailableLocales(),
            'interfaces' => OrigineInterfaces::asSelectArray(),
            'selectedLocales' => $this->selectedLocales,
        ]);
    }

    public function hydrate(): void
    {
        $this->refreshPendingExportState();
    }

    private function sanitizeErrorMessage(string $message): string
    {
        // Remove SQL queries and technical details that might contain JS keywords
        if (str_contains($message, 'SQL:')) {
            $pos = strpos($message, '(Connection:');
            if ($pos !== false) {
                $message = substr($message, 0, $pos);
            }
        }

        // Remove problematic keywords that could be interpreted as JS
        $problematicKeywords = ['import', 'export', 'function', 'class', 'const', 'let', 'var'];
        foreach ($problematicKeywords as $keyword) {
            $message = str_ireplace($keyword, str_repeat('*', strlen($keyword)), $message);
        }

        return trim($message);
    }

    /**
     * @return LengthAwarePaginator<int, TranslationKey>
     */
    private function getTranslations(): LengthAwarePaginator
    {
        $query = TranslationKey::query()
            ->with('values')
            ->forInterface($this->selectedInterface);

        if ($this->search !== '' && $this->search !== '0') {
            $query->where(function ($q): void {
                $q->where('key', 'ilike', '%'.$this->search.'%')
                    ->orWhere('group', 'ilike', '%'.$this->search.'%')
                    ->orWhereHas('values', function ($q): void {
                        $q->where('value', 'ilike', '%'.$this->search.'%');
                    });
            });
        }

        return $query->orderBy('group')
            ->orderBy('key')
            ->paginate(20);
    }

    /**
     * @return array<string, array{code: string, name: string, flag: string}>
     */
    private function getAvailableLocales(): array
    {
        $locales = [];
        foreach (Languages::getValues() as $locale) {
            $locales[$locale] = [
                'code' => $locale,
                'name' => Languages::nativeName((string) $locale),
                'flag' => Languages::flag((string) $locale),
            ];
        }

        /** @var array<string, array{code: string, name: string, flag: string}> */
        $filteredLocales = [];
        foreach ($locales as $key => $locale) {
            if (is_string($key) && is_array($locale) &&
                array_key_exists('code', $locale) && is_string($locale['code'])) {
                $filteredLocales[$key] = [
                    'code' => $locale['code'],
                    'name' => $locale['name'],
                    'flag' => $locale['flag'],
                ];
            }
        }

        return $filteredLocales;
    }

    public function updatedSelectedInterface(): void
    {
        $this->resetPage();
        $this->refreshPendingExportState();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedLocales(): void
    {
        $this->resetPage();
    }

    public function deleteKey(int|string $keyId): void
    {
        $translationKey = TranslationKey::find($keyId);
        if ($translationKey instanceof TranslationKey && $translationKey->interface_origin === $this->selectedInterface) {
            app(TranslationKeyService::class)->delete($translationKey);
            $this->dispatch('translation-deleted');
            $this->markPendingExport('delete');
        }
    }

    public function showAddKey(): void
    {
        $this->showAddKeyModal = true;
        $this->reset(['newKey', 'showAllSecondaryLanguagesForNew']);
        $this->initializeNewValues();
    }

    private function initializeNewValues(): void
    {
        $this->newValues = [];
        foreach (Languages::getValues() as $locale) {
            if (is_string($locale)) {
                $this->newValues[$locale] = '';
            }
        }
    }

    public function addKey(): void
    {
        $this->validate([
            'newKey' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_]+\.[a-zA-Z0-9_\.]+$/'],
        ], [
            'newKey.regex' => 'Le format de la clé est invalide. Utilisez le format: groupe.clé (ex: validation.required)',
        ]);

        // Split the key by first dot to extract group and key
        $firstDotPos = strpos($this->newKey, '.');

        if ($firstDotPos === false) {
            // This should not happen due to regex validation, but just in case
            $this->addError('newKey', 'Le format de la clé est invalide. Utilisez le format: groupe.clé');

            return;
        }

        $group = substr($this->newKey, 0, $firstDotPos);
        $key = substr($this->newKey, $firstDotPos + 1);

        // Check if key already exists for this interface
        $exists = TranslationKey::where('key', $key)
            ->where('group', $group)
            ->where('interface_origin', $this->selectedInterface)
            ->exists();

        if ($exists) {
            $this->addError('newKey', 'Cette clé existe déjà pour l\'interface sélectionnée.');

            return;
        }

        $keyService = app(TranslationKeyService::class);
        $valueService = app(TranslationValueService::class);

        $translationKey = $keyService->create([
            'key' => $key,
            'group' => $group,
            'interface_origin' => $this->selectedInterface,
        ]);

        // Add values for each locale
        foreach ($this->newValues as $locale => $value) {
            if (! empty($value)) {
                $valueService->create([
                    'translation_key_id' => $translationKey->id,
                    'locale' => $locale,
                    'value' => $value,
                ]);
            }
        }

        $this->reset(['newKey', 'showAddKeyModal']);
        $this->initializeNewValues();
        $this->dispatch('translation-added');
        $this->markPendingExport('create');
    }

    public function showExport(): void
    {
        $this->showExportModal = true;
    }

    public function export(): StreamedResponse
    {
        $exportAction = app(ExportTranslationsAction::class);
        $data = $exportAction->execute($this->selectedInterface);

        $filename = sprintf('translations_%s_%s.json', $this->selectedInterface, date('Y-m-d_H-i-s'));

        // Close the modal after triggering download
        $this->showExportModal = false;

        return response()->streamDownload(function () use ($data): void {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function showImport(): void
    {
        Log::info('showImport method called', [
            'selectedInterface' => $this->selectedInterface,
            'current_showImportModal' => $this->showImportModal,
        ]);

        $this->showImportModal = true;
        $this->showPreview = false;
        $this->previewData = [];
        $this->importType = 'multilingual';
        $this->importInterface = $this->selectedInterface;
        $this->importFile = null; // Reset file on modal open

        Log::info('showImport method completed', [
            'showImportModal' => $this->showImportModal,
            'importInterface' => $this->importInterface,
        ]);
    }

    public function updatedImportFile(): void
    {
        Log::info('Import file updated', [
            'file' => $this->importFile ? [
                'name' => $this->importFile->getClientOriginalName(),
                'size' => $this->importFile->getSize(),
                'mime' => $this->importFile->getMimeType(),
            ] : 'No file',
        ]);
    }

    public function previewImport(): void
    {
        Log::info('PreviewImport called', [
            'importFile' => $this->importFile ? 'File present' : 'No file',
            'importType' => $this->importType,
            'importInterface' => $this->importInterface,
        ]);

        try {
            $this->validate([
                'importFile' => 'required|file|mimes:json|max:10240',
                'importType' => 'required|in:multilingual,single',
                'importInterface' => 'required|in:'.implode(',', OrigineInterfaces::getValues()),
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            foreach ($errors as $field => $messages) {
                foreach ($messages as $message) {
                    // Traduire et améliorer les messages d'erreur
                    if ($field === 'importInterface' && str_contains($message, 'validation.in')) {
                        $allowedValues = implode(', ', OrigineInterfaces::getValues());
                        $this->addError($field, "L'interface '{$this->importInterface}' n'est pas valide. Les valeurs acceptées sont : {$allowedValues}");
                    } elseif ($field === 'importFile' && str_contains($message, 'validation.required')) {
                        $this->addError($field, 'Veuillez sélectionner un fichier JSON à importer.');
                    } elseif ($field === 'importFile' && str_contains($message, 'validation.mimes')) {
                        $this->addError($field, 'Le fichier doit être au format JSON.');
                    } elseif ($field === 'importFile' && str_contains($message, 'validation.max')) {
                        $this->addError($field, 'Le fichier ne doit pas dépasser 10 MB.');
                    } elseif ($field === 'importType' && str_contains($message, 'validation.in')) {
                        $this->addError($field, "Le type d'import '{$this->importType}' n'est pas valide. Utilisez 'multilingual' ou 'single'.");
                    } else {
                        $this->addError($field, $message);
                    }
                }
            }

            return;
        }

        if ($this->importFile === null) {
            return;
        }

        $content = file_get_contents($this->importFile->getRealPath());
        if ($content === false) {
            $this->addError('importFile', 'Impossible de lire le contenu du fichier. Vérifiez que le fichier n\'est pas corrompu.');

            return;
        }
        $filename = $this->importFile->getClientOriginalName();

        // Store file content and name for later use
        $this->importFileContent = $content;
        $this->importFileName = $filename;

        try {
            // Use the DTO to parse the file
            $dto = ImportTranslationDTO::fromFileUpload(
                $content,
                $filename,
                $this->importInterface,
                $this->importType,
                true, // preview mode
                $this->updateExistingValues
            );

            $importAction = app(ImportTranslationsAction::class);

            $this->previewData = $importAction->execute([
                'interface' => $dto->interfaceOrigin,
                'translations' => $dto->translations,
                'update_existing_values' => $this->updateExistingValues,
            ], $dto->interfaceOrigin, true);

            $this->showPreview = true;
        } catch (InvalidArgumentException $e) {
            // Message d'erreur plus détaillé pour les erreurs d'arguments
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'filename format')) {
                $this->addError('importFile', "Format de nom de fichier invalide. Pour un import 'single', le fichier doit être nommé selon le format : xx.json (ex: fr.json, en.json)");
            } elseif (str_contains($errorMessage, 'JSON')) {
                $this->addError('importFile', 'Le fichier JSON est invalide ou mal formaté. Vérifiez la syntaxe JSON.');
            } else {
                $this->addError('importFile', 'Erreur de validation : '.$errorMessage);
            }
        } catch (Exception $e) {
            // Log l'erreur complète pour le debug
            Log::error('Import file processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $filename,
                'interface' => $this->importInterface,
                'type' => $this->importType,
            ]);

            // Message d'erreur plus informatif
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'validation.in')) {
                $this->addError('importFile', "Erreur de validation : Une ou plusieurs valeurs dans le fichier ne sont pas acceptées. Vérifiez que l'interface correspond bien aux données.");
            } else {
                $this->addError('importFile', 'Erreur lors du traitement du fichier : '.$errorMessage);
            }
        }
    }

    public function testButton(): void
    {
        Log::info('Test button clicked!');
        $this->dispatch('import-success', ['message' => 'Test réussi!']);
    }

    public function confirmImport(): void
    {
        Log::info('confirmImport called', [
            'hasPreviewData' => $this->previewData !== [],
            'hasFileContent' => ! empty($this->importFileContent),
            'hasFileName' => ! empty($this->importFileName),
        ]);

        if ($this->previewData === [] || empty($this->importFileContent) || empty($this->importFileName)) {
            Log::warning('confirmImport aborted: missing data', [
                'previewData' => $this->previewData === [],
                'fileContent' => empty($this->importFileContent),
                'fileName' => empty($this->importFileName),
            ]);
            $this->dispatch('import-error', ['message' => $this->sanitizeErrorMessage('Aucun fichier ou aperçu disponible. Veuillez recommencer.')]);

            return;
        }

        $content = $this->importFileContent;
        $filename = $this->importFileName;

        try {
            // Use the DTO to parse the file
            $dto = ImportTranslationDTO::fromFileUpload(
                $content,
                $filename,
                $this->importInterface,
                $this->importType,
                false, // not preview mode
                $this->updateExistingValues
            );

            $importAction = app(ImportTranslationsAction::class);

            $result = $importAction->execute([
                'interface' => $dto->interfaceOrigin,
                'translations' => $dto->translations,
                'update_existing_values' => $this->updateExistingValues,
            ], $dto->interfaceOrigin, false);

            $this->reset(['showImportModal', 'importFile', 'previewData', 'showPreview', 'importType', 'importInterface', 'importFileContent', 'importFileName', 'updateExistingValues']);
            $this->dispatch('import-success', ['message' => 'Import réussi !', 'summary' => $result['summary']]);

            // Refresh the translations list
            $this->resetPage();
            $this->markPendingExport('import');
        } catch (Exception $e) {
            Log::error('Import error: '.$e->getMessage(), ['exception' => $e]);
            $this->addError('importFile', 'Erreur lors de l\'import: '.$e->getMessage());
            $this->dispatch('import-error', ['message' => $this->sanitizeErrorMessage($e->getMessage())]);
        }
    }

    public function cancelImport(): void
    {
        $this->reset(['showImportModal', 'importFile', 'previewData', 'showPreview', 'importType', 'importInterface', 'importFileContent', 'importFileName', 'updateExistingValues']);
    }

    public function openEditDrawer(int|string $keyId): void
    {
        $this->editingKey = TranslationKey::with('values')->find($keyId);

        if (! $this->editingKey instanceof TranslationKey) {
            $this->dispatch('translation-error', ['message' => 'Clé de traduction introuvable']);

            return;
        }

        $this->editingValues = [];
        $this->showAllSecondaryLanguages = false;

        // Load all current values
        foreach ($this->editingKey->values as $value) {
            $this->editingValues[$value->locale] = $value->value;
        }

        // Initialize empty values for missing locales
        foreach (Languages::getValues() as $locale) {
            if (! array_key_exists($locale, $this->editingValues)) {
                $this->editingValues[$locale] = '';
            }
        }

        $this->showEditDrawer = true;
    }

    public function closeEditDrawer(): void
    {
        $this->showEditDrawer = false;
        $this->editingKey = null;
        $this->editingValues = [];
        $this->showAllSecondaryLanguages = false;
    }

    public function toggleSecondaryLanguages(): void
    {
        $this->showAllSecondaryLanguages = ! $this->showAllSecondaryLanguages;
    }

    public function saveTranslations(): void
    {
        if (! $this->editingKey instanceof TranslationKey) {
            return;
        }

        $valueService = app(TranslationValueService::class);

        foreach ($this->editingValues as $locale => $value) {
            $existingValue = $this->editingKey->values()
                ->where('locale', $locale)
                ->first();

            if ($existingValue) {
                if (empty($value)) {
                    // Delete empty values - TranslationValueService has no delete method
                    $existingValue->delete();
                } elseif ($existingValue instanceof TranslationValue) {
                    // Update existing value
                    $valueService->update($existingValue, ['value' => $value]);
                }
            } elseif (! empty($value)) {
                // Create new value
                $valueService->create([
                    'translation_key_id' => $this->editingKey->id,
                    'locale' => $locale,
                    'value' => $value,
                ]);
            }
        }

        $this->closeEditDrawer();
        $this->dispatch('translation-saved');
        $this->markPendingExport('update');
    }

    /**
     * Open Export to S3 Modal
     */
    public function openExportToS3Modal(): void
    {
        // Initialize selected interfaces with all interfaces checked by default
        $this->selectedInterfacesForExport = [
            'mobile' => true,
            'web_financer' => true,
            'web_beneficiary' => true,
        ];

        $this->showExportToS3Modal = true;
    }

    /**
     * Export selected interfaces to S3
     */
    public function exportToS3(): void
    {
        $s3Service = null;
        try {
            // Filter only selected interfaces
            $interfacesToExport = array_keys(array_filter($this->selectedInterfacesForExport));

            if ($interfacesToExport === []) {
                $this->dispatch('toastr-error', message: 'Veuillez sélectionner au moins une interface à exporter.');

                return;
            }

            $s3Service = app(S3StorageService::class);
            $exportAction = app(ExportTranslationsAction::class);
            $exportedCount = 0;

            foreach ($interfacesToExport as $interface) {
                // Export all translations for the interface using the unified format
                $exportData = $exportAction->execute($interface);
                $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                if (! is_string($json)) {
                    throw new Exception("Failed to encode translations for interface: {$interface}");
                }

                // Generate filename with timestamp
                $timestamp = date('Y-m-d_His');
                $filename = "{$interface}_{$timestamp}.json";

                // Upload to S3
                $uploaded = $s3Service->uploadMigrationFile(
                    $interface,
                    $filename,
                    $json
                );

                if ($uploaded) {
                    Log::info("Exported {$interface} translations to S3: {$filename}");
                    $exportedCount++;
                } else {
                    throw new Exception("Failed to upload file to S3: {$filename}");
                }
            }

            // Close modal and show success message
            $this->showExportToS3Modal = false;
            $this->dispatch('export-s3-success', [
                'message' => "Exporté {$exportedCount} interface(s) vers S3 avec succès.",
            ]);

            $this->hasPendingExport = false;
            $this->pendingChangeSource = null;
            $this->refreshPendingExportState();

        } catch (Exception $e) {
            // Log complete error details with stack trace
            Log::error('Error exporting to S3', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'interface' => $this->selectedInterface,
                'filename' => $filename ?? 'unknown',
                'trace' => $e->getTraceAsString(),
                'disk' => method_exists($s3Service, 'getDisk') ? $s3Service->getDisk() : 'unknown',
                'previous_error' => $e->getPrevious() instanceof Throwable ? $e->getPrevious()->getMessage() : null,
            ]);

            // Re-throw with more context for better debugging
            $errorMessage = sprintf(
                "Error exporting to S3: %s\nInterface: %s\nFilename: %s\nLocation: %s:%d",
                $e->getMessage(),
                $this->selectedInterface,
                $filename ?? 'unknown',
                $e->getFile(),
                $e->getLine()
            );

            $this->dispatch('export-s3-error', [
                'message' => $errorMessage,
            ]);

            // Re-throw to get full stack trace in logs
            throw new Exception($errorMessage, 0, $e);
        }
    }

    private function markPendingExport(string $source): void
    {
        $this->hasPendingExport = true;
        $this->pendingChangeSource = $source;
    }

    private function refreshPendingExportState(): void
    {
        try {
            $latestContentUpdate = $this->getLatestContentUpdateTimestamp($this->selectedInterface);
            $lastExportTimestamp = $this->getLastExportTimestamp($this->selectedInterface);
        } catch (Exception $e) {
            Log::warning('Unable to evaluate pending export state', [
                'interface' => $this->selectedInterface,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if (! $latestContentUpdate instanceof Carbon) {
            $this->hasPendingExport = false;
            $this->pendingChangeSource = null;

            return;
        }

        if (! $lastExportTimestamp instanceof Carbon || $latestContentUpdate->greaterThan($lastExportTimestamp)) {
            $this->hasPendingExport = true;
            $this->pendingChangeSource ??= 'pending';
        } else {
            $this->hasPendingExport = false;
            $this->pendingChangeSource = null;
        }
    }

    private function getLatestContentUpdateTimestamp(string $interface): ?Carbon
    {
        $latestKeyUpdate = TranslationKey::where('interface_origin', $interface)->max('updated_at');

        $latestValueUpdate = TranslationValue::whereHas('key', function ($query) use ($interface): void {
            $query->where('interface_origin', $interface);
        })->max('updated_at');

        $timestamps = collect([$latestKeyUpdate, $latestValueUpdate])
            ->filter()
            ->map(fn ($value): Carbon => $value instanceof Carbon ? $value : Carbon::parse($value));

        if ($timestamps->isEmpty()) {
            return null;
        }

        return $timestamps->max();
    }

    private function getLastExportTimestamp(string $interface): ?Carbon
    {
        $files = app(S3StorageService::class)->listMigrationFiles($interface);

        $timestamps = collect($files)
            ->filter(fn ($path): bool => is_string($path) && ! str_contains($path, 'manifest.json'))
            ->map(function (string $path): ?Carbon {
                return $this->extractTimestampFromFilename($path);
            })
            ->filter();

        if ($timestamps->isEmpty()) {
            return null;
        }

        return $timestamps->max();
    }

    private function extractTimestampFromFilename(string $path): ?Carbon
    {
        $filename = basename($path);

        if (preg_match('/(\d{4}-\d{2}-\d{2}_[0-9\-]{6,8})/', $filename, $matches) !== 1) {
            return null;
        }

        $raw = $matches[1];
        $formats = ['Y-m-d_His', 'Y-m-d_H-i-s'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $raw, config('app.timezone'));
            } catch (Exception) {
                // Try next format
            }
        }

        return null;
    }

    /**
     * Sync translations from S3
     */
    public function syncFromS3(): void
    {
        try {
            $this->dispatch('sync-s3-started');

            // This will trigger the actual sync process
            // The implementation should be done via the TranslationMigrationService

            $this->dispatch('sync-s3-success', [
                'message' => 'Synchronisation avec S3 lancée. Vérifiez les migrations dans le panneau de migrations.',
            ]);

        } catch (Exception $e) {
            Log::error('Error syncing from S3: '.$e->getMessage());
            $this->dispatch('sync-s3-error', [
                'message' => 'Erreur lors de la synchronisation: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Handle migration-applied event to reset pending export state
     */
    public function handleMigrationApplied(): void
    {
        // Reset hasPendingExport since migration applies latest S3 content
        $this->hasPendingExport = false;
        $this->pendingChangeSource = null;

        // Refresh the pending export state to recalculate based on timestamps
        $this->refreshPendingExportState();

        Log::info('Migration applied - pending export state reset', [
            'interface' => $this->selectedInterface,
            'hasPendingExport' => $this->hasPendingExport,
        ]);
    }

    /**
     * Check if translations can be edited (based on environment)
     */
    public function canEditTranslations(): bool
    {
        return $this->environmentService->canEditTranslations();
    }

    /**
     * Check if a migration was recently applied (within last 5 minutes)
     */
    public function hasRecentMigrations(): bool
    {
        if (! session()->has('last_migration_ran')) {
            return false;
        }

        $lastMigration = session()->get('last_migration_ran');

        return abs(now()->diffInMinutes($lastMigration)) < 5;
    }
}
