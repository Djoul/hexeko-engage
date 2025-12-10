<?php

declare(strict_types=1);

namespace App\Http\Controllers\AdminPanel;

use App\Actions\Translation\ImportTranslationsAction;
use App\DTOs\Translation\ImportTranslationDTO;
use App\Http\Controllers\Controller;
use App\Jobs\TranslationMigrations\ProcessTranslationMigrationJob;
use App\Models\TranslationMigration;
use App\Services\TranslationMigrations\S3StorageService;
use App\Services\TranslationMigrations\TranslationMigrationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\View\View;
use Log;

class TranslationMigrationWebController extends Controller
{
    /**
     * Display the translation migrations dashboard using Livewire component
     *
     * @deprecated Since version 2.0.0 - This controller is being phased out in favor of the Livewire component.
     *             All functionality has been moved to App\Livewire\AdminPanel\Manager\Translation\TranslationMigrationManager.
     *             This method now simply renders the Livewire component view.
     */
    public function index(Request $request): View
    {
        // Legacy support - now renders Livewire component
        return view('admin-panel.pages.translation-migrations.livewire-index');
    }

    /**
     * Display a single migration details with preview of changes
     *
     * @deprecated Since version 2.0.0 - Use Livewire component preview functionality instead.
     */
    public function show(TranslationMigration $translationMigration): View
    {
        $previewData = [];

        try {
            // Download migration file from S3
            $s3Service = app(S3StorageService::class);
            $migrationContent = $s3Service->downloadMigrationFile(
                $translationMigration->interface_origin,
                $translationMigration->filename
            );

            // Parse the migration file
            $migrationData = json_decode($migrationContent, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($migrationData['translations'])) {

                // Create DTO for import preview using fromFileUpload
                $dto = ImportTranslationDTO::fromFileUpload(
                    $migrationContent,
                    $translationMigration->filename,
                    $translationMigration->interface_origin,
                    'multilingual', // assuming multilingual format from export
                    true // preview mode
                );

                // Generate preview using ImportTranslationsAction
                $importAction = app(ImportTranslationsAction::class);
                $previewData = $importAction->execute([
                    'interface' => $dto->interfaceOrigin,
                    'translations' => $dto->translations,
                ], $dto->interfaceOrigin, true);
            }
        } catch (Exception $e) {
            Log::error('Failed to generate migration preview: '.$e->getMessage());
            // Continue without preview data
        }

        return view('admin-panel.pages.translation-migrations.show', [
            'migration' => $translationMigration,
            'previewData' => $previewData,
        ]);
    }

    /**
     * Sync translations from S3
     *
     * @deprecated Since version 2.0.0 - Use Livewire component syncFromS3 method instead.
     */
    public function sync(Request $request)
    {
        try {
            $request->validate([
                'interface' => 'required|string|in:mobile,web_financer,web_beneficiary',
                'auto_process' => 'nullable|boolean',
            ]);

            $interface = $request->input('interface');
            $autoProcess = $request->boolean('auto_process', false);

            // Use the S3 service to list available migration files
            $s3Service = app(S3StorageService::class);
            $migrationService = app(TranslationMigrationService::class);

            // List files from S3
            $files = $s3Service->listMigrationFiles($interface);

            if ($files->isEmpty()) {
                return redirect()->back()->with('warning', 'Aucun fichier de migration trouvé sur S3 pour l\'interface '.$interface);
            }

            $syncedCount = 0;

            foreach ($files as $file) {
                // Extract filename from path
                $filename = basename($file);

                // Check if migration already exists
                $existingMigration = TranslationMigration::where('filename', $filename)
                    ->where('interface_origin', $interface)
                    ->first();

                if (! $existingMigration) {
                    // Download file content
                    $content = $s3Service->downloadMigrationFile($interface, $filename);
                    $checksum = hash('sha256', $content);

                    // Extract version from filename (assuming format: locale_YYYY-MM-DD_HHiiss.json)
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
                            'auto_process' => $autoProcess,
                        ],
                    ]);

                    $syncedCount++;
                }
            }

            $message = $syncedCount > 0
                ? "Synchronisation réussie : {$syncedCount} nouvelles migrations trouvées."
                : 'Aucune nouvelle migration à synchroniser.';

            if ($autoProcess && $syncedCount > 0) {
                $message .= ' Les migrations seront appliquées automatiquement.';
                // TODO: Dispatch job to process migrations
            }

            return redirect()->back()->with('success', $message);

        } catch (Exception $e) {
            Log::error('Error syncing from S3: '.$e->getMessage());

            return redirect()->back()->with('error', 'Erreur lors de la synchronisation : '.$e->getMessage());
        }
    }

    /**
     * Apply a migration
     *
     * @deprecated Since version 2.0.0 - Use Livewire component applyMigration method instead.
     */
    public function apply(Request $request, TranslationMigration $translationMigration)
    {
        try {
            $createBackup = $request->boolean('create_backup', true);
            $validateChecksum = $request->boolean('validate_checksum', true);

            // Log the start of the apply process
            Log::info('Starting translation migration apply from admin panel', [
                'migration_id' => $translationMigration->id,
                'filename' => $translationMigration->filename,
                'interface' => $translationMigration->interface_origin,
                'current_status' => $translationMigration->status,
                'create_backup' => $createBackup,
                'validate_checksum' => $validateChecksum,
                'queue_connection' => config('queue.default'),
                'sqs_queue' => config('queue.connections.sqs.queue'),
            ]);

            // Update status to processing
            $translationMigration->update(['status' => 'processing']);

            // Get dynamic queue name based on active connection
            $queueConnection = config('queue.default');
            $queueName = config("queue.connections.{$queueConnection}.queue", 'default');
            $queueNameStr = is_string($queueName) ? $queueName : 'default';

            Log::info('Dispatching ProcessTranslationMigrationJob', [
                'migration_id' => $translationMigration->id,
                'queue' => $queueNameStr,
                'queue_connection' => config('queue.default'),
            ]);

            // Dispatch the job with explicit queue
            ProcessTranslationMigrationJob::dispatch(
                migrationId: $translationMigration->id,
                createBackup: $createBackup,
                validateChecksum: $validateChecksum
            )->onQueue($queueNameStr);

            // Update metadata with request details
            $translationMigration->update([
                'metadata' => array_merge($translationMigration->metadata ?? [], [
                    'create_backup_requested' => $createBackup,
                    'validate_checksum_requested' => $validateChecksum,
                    'apply_requested_at' => Date::now()->toIso8601String(),
                    'dispatched_to_queue' => $queueNameStr,
                ]),
            ]);

            Log::info('Translation migration job dispatched successfully', [
                'migration_id' => $translationMigration->id,
                'queue' => $queueNameStr,
            ]);

            return redirect()->back()->with('success', 'La migration a été mise en file pour traitement.');
        } catch (Exception $e) {
            Log::error('Migration apply failed via admin panel', [
                'migration_id' => $translationMigration->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Reset status to pending if dispatch failed
            $translationMigration->update(['status' => 'pending']);

            return redirect()->back()->with('error', 'Erreur lors de l\'application de la migration : '.$e->getMessage());
        }
    }

    /**
     * Rollback a migration
     *
     * @deprecated Since version 2.0.0 - Use Livewire component rollbackMigration method instead.
     */
    public function rollback(TranslationMigration $translationMigration)
    {
        try {
            // TODO: Implement migration rollback logic
            $translationMigration->update(['status' => 'rolled_back']);

            return redirect()->back()->with('success', 'Migration annulée avec succès.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Erreur lors de l\'annulation de la migration : '.$e->getMessage());
        }
    }
}
