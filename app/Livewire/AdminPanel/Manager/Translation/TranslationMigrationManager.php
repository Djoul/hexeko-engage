<?php

declare(strict_types=1);

namespace App\Livewire\AdminPanel\Manager\Translation;

use App\Actions\Translation\ImportTranslationsAction;
use App\DTOs\Translation\ImportTranslationDTO;
use App\Jobs\TranslationMigrations\ProcessTranslationMigrationJob;
use App\Models\TranslationMigration;
use App\Services\EnvironmentService;
use App\Services\TranslationMigrations\S3StorageService;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('admin-panel.layouts.app')]
class TranslationMigrationManager extends Component
{
    use WithPagination;

    /** @var LengthAwarePaginator<int, TranslationMigration>|null */
    private ?LengthAwarePaginator $cachedMigrations = null;

    // Filters
    public string $selectedInterface = '';

    public string $selectedStatus = '';

    public string $search = '';

    public ?string $dateFrom = null;

    // Pagination
    public int $perPage = 20;

    // Modal states
    public bool $showSyncModal = false;

    public bool $showApplyModal = false;

    public bool $showRollbackModal = false;

    public bool $showPreviewDrawer = false;

    // Selected migration
    public ?TranslationMigration $selectedMigration = null;

    /** @var array<int> */
    public array $selectedMigrations = [];

    public bool $selectAll = false;

    public string $activePreset = 'custom';

    /** @var array<string, int> */
    public array $counters = [
        'pending' => 0,
        'failed_recent' => 0,
        'processing' => 0,
    ];

    // Preview data
    /** @var array<string, mixed> */
    public array $previewData = [];

    // Sync options
    public string $syncInterface = '';

    /** @var array<string, bool> */
    public array $selectedInterfacesForSync = [];

    public bool $autoProcess = false;

    // Apply options
    public bool $createBackup = true;

    public bool $validateChecksum = true;

    // Environment and migration state detection
    private EnvironmentService $environmentService;

    /**
     * Query string properties
     *
     * @var array<string, array<string, mixed>>
     */
    protected $queryString = [
        'selectedInterface' => ['except' => ''],
        'selectedStatus' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    /**
     * Validation rules
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'syncInterface' => 'required|in:mobile,web_financer,web_beneficiary',
            'selectedMigration.id' => 'required|exists:translation_migrations,id',
            'createBackup' => 'boolean',
            'validateChecksum' => 'boolean',
        ];
    }

    /**
     * Constructor
     */
    public function boot(EnvironmentService $environmentService): void
    {
        $this->environmentService = $environmentService;
    }

    /**
     * Mount the component
     */
    public function mount(): void
    {
        $this->refreshCounters();
    }

    /**
     * Render the component
     */
    public function render(): View
    {
        $this->refreshCounters();

        $this->cachedMigrations = null;
        $migrations = $this->getMigrations();

        return view('livewire.admin-panel.manager.translation.migration-enhanced', [
            'migrations' => $migrations,
            'interfaces' => $this->getInterfacesProperty(),
            'statuses' => $this->getStatusesProperty(),
        ]);
    }

    /**
     * Get paginated migrations based on filters
     *
     * @return LengthAwarePaginator<int, TranslationMigration>
     */
    private function getMigrations(): LengthAwarePaginator
    {
        if ($this->cachedMigrations instanceof LengthAwarePaginator) {
            return $this->cachedMigrations;
        }

        $query = TranslationMigration::query();

        // Apply interface filter
        if ($this->selectedInterface !== '') {
            $query->forInterface($this->selectedInterface);
        }

        // Apply status filter
        if ($this->selectedStatus !== '') {
            $query->where('status', $this->selectedStatus);
        }

        // Apply search filter
        if ($this->search !== '') {
            $query->where(function ($q): void {
                $q->where('filename', 'LIKE', "%{$this->search}%")
                    ->orWhere('version', 'LIKE', "%{$this->search}%");
            });
        }

        if ($this->dateFrom !== null && $this->dateFrom !== '') {
            try {
                $date = Date::parse($this->dateFrom)->toDateString();
                $query->whereDate('created_at', '>=', $date);
            } catch (Exception $e) {
                Log::warning('Invalid dateFrom value for translation migrations filter', [
                    'value' => $this->dateFrom,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->cachedMigrations = $query->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        return $this->cachedMigrations;
    }

    /**
     * Updated lifecycle hooks for filters
     */
    public function updatedSelectedInterface(): void
    {
        $this->resetPage();
        $this->selectedMigrations = [];
        $this->selectAll = false;
    }

    public function updatedSelectedStatus(): void
    {
        $this->resetPage();
        $this->selectedMigrations = [];
        $this->selectAll = false;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->selectedMigrations = [];
        $this->selectAll = false;
    }

    public function updatedSelectAll(bool $value): void
    {
        $this->selectedMigrations = $value ? $this->currentPageMigrationIds() : [];
    }

    public function updatedSelectedMigrations(): void
    {
        $this->selectedMigrations = array_values(array_map('intval', $this->selectedMigrations));

        $currentPageIds = $this->currentPageMigrationIds();
        $this->selectAll = $currentPageIds !== []
            && empty(array_diff($currentPageIds, $this->selectedMigrations));
    }

    public function applyPreset(string $preset): void
    {
        $this->activePreset = $preset;

        switch ($preset) {
            case 'to-apply':
                $this->selectedStatus = 'pending';
                $this->dateFrom = null;
                break;
            case 'failed-24h':
                $this->selectedStatus = 'failed';
                $this->dateFrom = Date::now()->subDay()->toDateString();
                break;
            case 'processing':
                $this->selectedStatus = 'processing';
                $this->dateFrom = null;
                break;
            default:
                $this->selectedStatus = '';
                $this->dateFrom = null;
                break;
        }

        $this->resetPage();
        $this->selectedMigrations = [];
        $this->selectAll = false;
    }

    public function exportSelected(): void
    {
        if ($this->selectedMigrations === []) {
            $this->dispatch('migration-error', ['message' => 'Please select at least one migration to export.']);

            return;
        }

        $selected = TranslationMigration::whereIn('id', $this->selectedMigrations)
            ->get(['id', 'filename', 'interface_origin']);

        $this->dispatch('migration-export-ready', [
            'migrations' => $selected->map(fn (TranslationMigration $migration): array => [
                'id' => $migration->id,
                'filename' => $migration->filename,
                'interface' => $migration->interface_origin,
            ])->values()->all(),
        ]);
    }

    public function applyBulk(): void
    {
        if ($this->selectedMigrations === []) {
            return;
        }

        $migrations = TranslationMigration::whereIn('id', $this->selectedMigrations)->get();

        foreach ($migrations as $migration) {
            if ($migration->status !== 'pending') {
                continue;
            }

            $this->applyMigration($migration->id);
        }

        $this->selectedMigrations = [];
        $this->selectAll = false;
    }

    public function retryFailed(): void
    {
        $ids = $this->selectedMigrations;

        if ($ids === []) {
            $ids = TranslationMigration::where('status', 'failed')->pluck('id')->all();
        }

        if ($ids === []) {
            $this->dispatch('migration-error', ['message' => 'No failed migrations to retry.']);

            return;
        }

        TranslationMigration::whereIn('id', $ids)
            ->where('status', 'failed')
            ->update([
                'status' => 'pending',
                'updated_at' => Date::now(),
            ]);

        $this->dispatch('migration-retried', ['message' => 'Failed migrations marked as pending.']);

        $this->selectedMigrations = [];
        $this->selectAll = false;
    }

    public function viewDetails(int $migrationId): void
    {
        $this->openPreview($migrationId);
    }

    public function downloadJson(int $migrationId): void
    {
        $migration = TranslationMigration::find($migrationId);

        if (! $migration instanceof TranslationMigration) {
            return;
        }

        try {
            $s3Service = app(S3StorageService::class);
            $content = $s3Service->downloadMigrationFile($migration->interface_origin, $migration->filename);

            $this->dispatch('download', [
                'filename' => $migration->filename,
                'content' => $content,
                'contentType' => 'application/json',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to download migration json: '.$e->getMessage());
            $this->dispatch('migration-error', ['message' => 'Unable to download migration file.']);
        }
    }

    public function openDrawer(int $migrationId): void
    {
        $this->viewDetails($migrationId);
    }

    /**
     * Show sync modal
     */
    public function openSyncModal(): void
    {
        $this->showSyncModal = true;
        $this->selectedInterfacesForSync = [
            'mobile' => true,
            'web_financer' => true,
            'web_beneficiary' => true,
        ];
        $this->autoProcess = false;
    }

    /**
     * Sync from S3
     */
    public function syncFromS3(): void
    {
        try {
            // Filter only selected interfaces
            $interfacesToSync = array_keys(array_filter($this->selectedInterfacesForSync));

            if ($interfacesToSync === []) {
                $this->dispatch('migration-error', ['message' => 'Veuillez sélectionner au moins une interface à synchroniser.']);

                return;
            }

            $s3Service = app(S3StorageService::class);
            $totalSyncedCount = 0;
            $interfaceSyncDetails = [];

            foreach ($interfacesToSync as $interface) {
                // List files from S3 for this interface
                $files = $s3Service->listMigrationFiles($interface);

                if ($files->isEmpty()) {
                    $interfaceSyncDetails[$interface] = ['found' => 0, 'synced' => 0];

                    continue;
                }

                $syncedCount = 0;
                $foundCount = $files->count();

                foreach ($files as $file) {
                    /** @phpstan-ignore-next-line */
                    $fileString = is_string($file) ? $file : (string) $file;
                    $filename = basename($fileString);

                    // Check if migration already exists
                    $exists = TranslationMigration::where('filename', $filename)
                        ->where('interface_origin', $interface)
                        ->exists();

                    if (! $exists) {
                        // Download file content
                        $content = $s3Service->downloadMigrationFile($interface, $filename);
                        $checksum = hash('sha256', $content);

                        // Extract version from filename
                        preg_match('/(\d{4}-\d{2}-\d{2}_\d{6})/', $filename, $matches);
                        $version = $matches[1] ?? date('Y-m-d_His');

                        // Create migration record
                        TranslationMigration::create([
                            'filename' => $filename,
                            'interface_origin' => $interface,
                            'version' => $version,
                            'checksum' => $checksum,
                            'status' => 'pending',
                            'metadata' => [
                                's3_path' => $file,
                                'synced_at' => now()->toIso8601String(),
                                'auto_process' => $this->autoProcess,
                            ],
                        ]);

                        $syncedCount++;
                        $totalSyncedCount++;
                    }
                }

                $interfaceSyncDetails[$interface] = ['found' => $foundCount, 'synced' => $syncedCount];
            }

            // Prepare success message
            if ($totalSyncedCount > 0) {
                $details = [];
                foreach ($interfaceSyncDetails as $interface => $counts) {
                    if ($counts['synced'] > 0) {
                        $details[] = "{$interface}: {$counts['synced']} nouvelles";
                    }
                }
                $message = "Synchronisation réussie : {$totalSyncedCount} migration(s) trouvée(s) (".implode(', ', $details).')';
            } else {
                $message = 'Aucune nouvelle migration à synchroniser.';
            }

            $this->dispatch('migration-synced', ['message' => $message]);
            $this->showSyncModal = false;
            $this->dispatch('$refresh');

        } catch (Exception $e) {
            Log::error('Error syncing from S3: '.$e->getMessage());
            $this->dispatch('migration-error', ['message' => 'Error during synchronization: '.$e->getMessage()]);
        }
    }

    /**
     * Show apply modal
     */
    public function openApplyModal(int $migrationId): void
    {
        $this->selectedMigration = TranslationMigration::find($migrationId);
        if ($this->selectedMigration instanceof TranslationMigration) {
            $this->showApplyModal = true;
            $this->createBackup = true;
            $this->validateChecksum = true;
        }
    }

    /**
     * Apply migration
     */
    public function applyMigration(?int $migrationId = null): void
    {
        if ($migrationId !== null) {
            $this->selectedMigration = TranslationMigration::find($migrationId);
        }

        if (! $this->selectedMigration instanceof TranslationMigration) {
            return;
        }

        if ($this->selectedMigration->status !== 'pending') {
            $this->dispatch('migration-error', ['message' => 'Only pending migrations can be applied.']);

            return;
        }

        try {
            // Update status to processing
            $this->selectedMigration->update(['status' => 'processing']);

            // Get dynamic queue name
            /** @phpstan-ignore-next-line */
            $queueConnection = (string) config('queue.default', 'sync');
            $queueName = config("queue.connections.{$queueConnection}.queue", 'default');
            $queueNameStr = is_string($queueName) ? $queueName : 'default';

            // Dispatch job
            ProcessTranslationMigrationJob::dispatch(
                migrationId: $this->selectedMigration->id,
                createBackup: $this->createBackup,
                validateChecksum: $this->validateChecksum
            )->onQueue($queueNameStr);

            // Update metadata
            $this->selectedMigration->update([
                'metadata' => array_merge($this->selectedMigration->metadata ?? [], [
                    'create_backup_requested' => $this->createBackup,
                    'validate_checksum_requested' => $this->validateChecksum,
                    'apply_requested_at' => Date::now()->toIso8601String(),
                    'dispatched_to_queue' => $queueNameStr,
                ]),
            ]);

            // Set session timestamp for recent migration detection
            Session::put('last_migration_ran', now());

            $this->dispatch('migration-applied', [
                'message' => 'Migration has been queued for processing.',
                'migrationId' => $this->selectedMigration->id,
            ]);

            $this->showApplyModal = false;
            $this->dispatch('$refresh');

        } catch (Exception $e) {
            Log::error('Migration apply failed: '.$e->getMessage());
            $this->selectedMigration->update(['status' => 'pending']);
            $this->dispatch('migration-error', ['message' => 'Error applying migration: '.$e->getMessage()]);
        }
    }

    /**
     * Show rollback modal
     */
    public function openRollbackModal(int $migrationId): void
    {
        $this->selectedMigration = TranslationMigration::find($migrationId);
        if ($this->selectedMigration && $this->selectedMigration->status === 'completed') {
            $this->showRollbackModal = true;
        }
    }

    /**
     * Rollback migration
     */
    public function rollbackMigration(?int $migrationId = null): void
    {
        if ($migrationId !== null) {
            $this->selectedMigration = TranslationMigration::find($migrationId);
        }

        if (! $this->selectedMigration instanceof TranslationMigration) {
            return;
        }

        try {
            $this->selectedMigration->update([
                'status' => 'rolled_back',
                'rolled_back_at' => now(),
            ]);

            $this->dispatch('migration-rolled-back', [
                'message' => 'Migration rolled back successfully.',
                'migrationId' => $this->selectedMigration->id,
            ]);

            $this->showRollbackModal = false;
            $this->dispatch('$refresh');

        } catch (Exception $e) {
            Log::error('Rollback failed: '.$e->getMessage());
            $this->dispatch('migration-error', ['message' => 'Error during rollback: '.$e->getMessage()]);
        }
    }

    /**
     * Show preview drawer
     */
    public function openPreview(int $migrationId): void
    {
        $this->selectedMigration = TranslationMigration::find($migrationId);

        if (! $this->selectedMigration instanceof TranslationMigration) {
            return;
        }

        try {
            // Load preview data from S3
            $s3Service = app(S3StorageService::class);
            $migrationContent = $s3Service->downloadMigrationFile(
                $this->selectedMigration->interface_origin,
                $this->selectedMigration->filename
            );

            // Parse migration file
            $migrationData = json_decode($migrationContent, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($migrationData) && array_key_exists('translations', $migrationData)) {

                // Create DTO for import preview
                $dto = ImportTranslationDTO::fromFileUpload(
                    $migrationContent,
                    $this->selectedMigration->filename,
                    $this->selectedMigration->interface_origin,
                    'multilingual',
                    true // preview mode
                );

                // Generate preview
                $importAction = app(ImportTranslationsAction::class);

                // Type-safe data preparation
                /** @var array<string, array<string, string>> $translations */
                $translations = $dto->translations;

                /** @var array<string, mixed> $previewResult */
                $previewResult = $importAction->execute([
                    'interface' => $dto->interfaceOrigin,
                    'translations' => $translations,
                ], $dto->interfaceOrigin, true);
                $this->previewData = $previewResult;
            }

            $this->showPreviewDrawer = true;

        } catch (Exception $e) {
            Log::error('Failed to generate preview: '.$e->getMessage());
            $this->previewData = [];
            $this->showPreviewDrawer = true; // Show drawer even if preview fails
        }
    }

    /**
     * Close all modals
     */
    public function closeModals(): void
    {
        $this->showSyncModal = false;
        $this->showApplyModal = false;
        $this->showRollbackModal = false;
        $this->showPreviewDrawer = false;
        $this->selectedMigration = null;
        $this->previewData = [];
    }

    /**
     * Refresh table
     */
    public function refreshTable(): void
    {
        $this->dispatch('$refresh');
    }

    private function refreshCounters(): void
    {
        $statusCounts = TranslationMigration::select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->toArray();

        $this->counters = [
            'pending' => (int) ($statusCounts['pending'] ?? 0),
            'failed_recent' => TranslationMigration::where('status', 'failed')
                ->where('updated_at', '>=', Date::now()->subDay())
                ->count(),
            'processing' => (int) ($statusCounts['processing'] ?? 0),
        ];
    }

    /**
     * @return array<int>
     */
    private function currentPageMigrationIds(): array
    {
        return $this->getMigrations()->getCollection()
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Computed property for interfaces
     *
     * @return array<string, string>
     */
    public function getInterfacesProperty(): array
    {
        return [
            'mobile' => 'Mobile',
            'web_financer' => 'Web Financer',
            'web_beneficiary' => 'Web Beneficiary',
        ];
    }

    /**
     * Computed property for statuses
     *
     * @return array<string, array{label: string, color: string}>
     */
    public function getStatusesProperty(): array
    {
        return [
            'pending' => ['label' => 'Pending', 'color' => 'warning'],
            'processing' => ['label' => 'Processing', 'color' => 'info'],
            'completed' => ['label' => 'Completed', 'color' => 'success'],
            'failed' => ['label' => 'Failed', 'color' => 'error'],
            'rolled_back' => ['label' => 'Rolled Back', 'color' => 'neutral'],
        ];
    }

    /**
     * Check if editing translations is allowed in current environment
     * Only staging environment allows editing
     */
    public function canEditTranslations(): bool
    {
        return $this->environmentService->canEditTranslations();
    }

    /**
     * Check if migrations were recently ran (within last 5 minutes)
     */
    public function hasRecentMigrations(): bool
    {
        if (! Session::has('last_migration_ran')) {
            return false;
        }

        $lastMigration = Session::get('last_migration_ran');

        return abs(now()->diffInMinutes($lastMigration)) < 5;
    }
}
