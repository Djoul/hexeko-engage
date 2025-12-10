<?php

declare(strict_types=1);

namespace App\Livewire\AdminPanel\Manager\Translation;

use App\Actions\Translation\ExportTranslationsAction;
use App\DTOs\Translation\ExportTranslationDTO;
use App\Enums\Languages;
use App\Enums\OrigineInterfaces;
// use App\Jobs\TranslationMigrations\ExportTranslationsToS3Job; // TODO: Implement S3 export job
use App\Models\TranslationKey;
use App\Services\TranslationMigrations\S3StorageService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class TranslationExport extends Component
{
    public bool $showExportModal = false;

    public bool $showExportToS3Modal = false;

    /** @var array<string, bool> */
    public array $selectedInterfacesForExport = [];

    /** @var array<string, bool> */
    public array $selectedLocales = [];

    public string $exportFormat = 'json';

    public string $exportInterface = 'all';

    public bool $includeEmptyTranslations = false;

    public bool $groupByInterface = true;

    public bool $isProcessing = false;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public int $pendingExportsCount = 0;

    protected $listeners = [
        'openExportModal' => 'openModal',
        'closeExportModal' => 'closeModal',
        'openS3ExportModal' => 'openS3Modal',
        'refreshExportStatus' => 'checkPendingExports',
    ];

    public function mount(): void
    {
        $this->initializeExportSettings();
        $this->checkPendingExports();
    }

    public function render(): View
    {
        return view('livewire.admin-panel.manager.translation.export', [
            'interfaces' => $this->getAvailableInterfaces(),
            'locales' => $this->getAvailableLocales(),
            'statistics' => $this->getExportStatistics(),
        ]);
    }

    public function openModal(): void
    {
        $this->showExportModal = true;
        $this->resetMessages();
    }

    public function closeModal(): void
    {
        $this->showExportModal = false;
        $this->resetMessages();
    }

    public function openS3Modal(): void
    {
        $this->showExportToS3Modal = true;
        $this->initializeS3ExportSettings();
        $this->resetMessages();
    }

    public function closeS3Modal(): void
    {
        $this->showExportToS3Modal = false;
        $this->resetMessages();
    }

    public function export(): void
    {
        $this->validate([
            'exportFormat' => 'required|in:json,csv,xlsx',
            'exportInterface' => 'required|string',
        ]);

        $this->isProcessing = true;
        $this->resetMessages();

        try {
            $dto = ExportTranslationDTO::from([
                'interface' => $this->exportInterface,
                'locales' => array_keys(array_filter($this->selectedLocales)),
                'format' => $this->exportFormat,
                'includeEmpty' => $this->includeEmptyTranslations,
                'groupByInterface' => $this->groupByInterface,
            ]);

            $result = app(ExportTranslationsAction::class)->execute($dto);

            // Trigger download
            $filename = sprintf(
                'translations_%s_%s.%s',
                $this->exportInterface,
                Carbon::now()->format('Y-m-d_His'),
                $this->exportFormat
            );

            $this->dispatch('download', [
                'filename' => $filename,
                'content' => $result['content'],
                'contentType' => $this->getContentType($this->exportFormat),
            ]);

            $this->successMessage = sprintf(
                'Successfully exported %d translations',
                $result['count'] ?? 0
            );

            // Log export activity
            Log::info('Translations exported', [
                'interface' => $this->exportInterface,
                'format' => $this->exportFormat,
                'count' => $result['count'] ?? 0,
                'user_id' => auth()->id(),
            ]);

            $this->closeModal();
        } catch (Exception $e) {
            Log::error('Translation export failed', [
                'error' => $e->getMessage(),
                'interface' => $this->exportInterface,
                'format' => $this->exportFormat,
            ]);

            $this->errorMessage = 'Export failed: '.$e->getMessage();
        } finally {
            $this->isProcessing = false;
        }
    }

    public function exportToS3(): void
    {
        $selectedInterfaces = array_keys(array_filter($this->selectedInterfacesForExport));

        if ($selectedInterfaces === []) {
            $this->errorMessage = 'Please select at least one interface to export';

            return;
        }

        $this->isProcessing = true;
        $this->resetMessages();

        try {
            // TODO: Implement S3 export job
            // foreach ($selectedInterfaces as $interface) {
            //     ExportTranslationsToS3Job::dispatch($interface, auth()->id())
            //         ->onQueue('translations');
            // }

            // For now, just show a message that this feature is not yet implemented
            $this->errorMessage = 'S3 export feature is not yet implemented';

            // Commented out until S3 export is implemented
            // $this->successMessage = sprintf(
            //     'Export to S3 initiated for %d interface(s). Check notifications for completion.',
            //     count($selectedInterfaces)
            // );

            // $this->pendingExportsCount = count($selectedInterfaces);

            // // Emit event to notify parent component
            // $this->dispatch('s3ExportInitiated', [
            //     'interfaces' => $selectedInterfaces,
            //     'count' => count($selectedInterfaces),
            // ]);

            $this->closeS3Modal();
        } catch (Exception $e) {
            Log::error('S3 export failed to initiate', [
                'error' => $e->getMessage(),
                'interfaces' => $selectedInterfaces,
            ]);

            $this->errorMessage = 'Failed to initiate S3 export: '.$e->getMessage();
        } finally {
            $this->isProcessing = false;
        }
    }

    public function toggleInterface(string $interface): void
    {
        $this->selectedInterfacesForExport[$interface] = ! ($this->selectedInterfacesForExport[$interface] ?? false);
    }

    public function toggleLocale(string $locale): void
    {
        $this->selectedLocales[$locale] = ! ($this->selectedLocales[$locale] ?? false);
    }

    public function selectAllInterfaces(): void
    {
        foreach (array_keys($this->getAvailableInterfaces()) as $key) {
            $this->selectedInterfacesForExport[$key] = true;
        }
    }

    public function deselectAllInterfaces(): void
    {
        $this->selectedInterfacesForExport = [];
    }

    public function selectAllLocales(): void
    {
        foreach (array_keys($this->getAvailableLocales()) as $locale) {
            $this->selectedLocales[$locale] = true;
        }
    }

    public function deselectAllLocales(): void
    {
        $this->selectedLocales = [];
    }

    public function checkPendingExports(): void
    {
        // Check S3 service for pending exports
        try {
            // TODO: Implement hasPendingExport method in S3StorageService
            // For now, we'll just set it to 0 to avoid errors
            $this->pendingExportsCount = 0;

            // Uncomment when hasPendingExport is implemented
            // $s3Service = app(S3StorageService::class);
            // $pendingCount = 0;
            //
            // foreach (array_keys($this->getAvailableInterfaces()) as $interface) {
            //     if ($s3Service->hasPendingExport($interface)) {
            //         $pendingCount++;
            //     }
            // }
            //
            // $this->pendingExportsCount = $pendingCount;
        } catch (Exception $e) {
            Log::error('Failed to check pending exports', ['error' => $e->getMessage()]);
            $this->pendingExportsCount = 0;
        }
    }

    private function initializeExportSettings(): void
    {
        // Select all locales by default
        foreach (array_keys($this->getAvailableLocales()) as $locale) {
            $this->selectedLocales[$locale] = true;
        }
    }

    private function initializeS3ExportSettings(): void
    {
        // Select common interfaces by default
        $this->selectedInterfacesForExport = [
            OrigineInterfaces::WEB_FINANCER => true,
            OrigineInterfaces::WEB_BENEFICIARY => true,
            OrigineInterfaces::MOBILE => true,
        ];
    }

    private function getAvailableInterfaces(): array
    {
        return [
            'all' => 'All Interfaces',
            OrigineInterfaces::WEB_FINANCER => 'Web Financer',
            OrigineInterfaces::WEB_BENEFICIARY => 'Web Beneficiary',
            OrigineInterfaces::MOBILE => 'Mobile',
        ];
    }

    private function getAvailableLocales(): array
    {
        return [
            Languages::ENGLISH => 'English',
            Languages::FRENCH => 'French',
            Languages::SPANISH => 'Spanish',
            Languages::GERMAN => 'German',
            Languages::ITALIAN => 'Italian',
            Languages::PORTUGUESE => 'Portuguese',
        ];
    }

    private function getExportStatistics(): array
    {
        $stats = [];

        try {
            foreach (array_keys($this->getAvailableInterfaces()) as $interface) {
                if ($interface === 'all') {
                    $stats['all'] = TranslationKey::count();
                } else {
                    $stats[$interface] = TranslationKey::where('interface_origin', $interface)->count();
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to get export statistics', ['error' => $e->getMessage()]);
        }

        return $stats;
    }

    private function getContentType(string $format): string
    {
        return match ($format) {
            'json' => 'application/json',
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }

    private function resetMessages(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }
}
