<?php

namespace App\Jobs;

use App\Events\CsvInvitedUsersImportCompleted;
use App\Mail\CsvImportErrorsMail;
use App\Models\Financer;
use App\Models\User;
use App\Services\CsvImportTrackerService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class HandleCsvImportBatchCompletionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $importId,
        public readonly string $financerId,
        public readonly string $userId,
        public readonly string $filePath,
        public readonly int $totalRows
    ) {}

    public function handle(): void
    {
        // Get complete import data from Redis tracker
        $tracker = app(CsvImportTrackerService::class);
        $importData = $tracker->getCompleteImportData($this->importId);

        if ($importData === null || $importData === []) {
            if (! app()->runningUnitTests()) {
                Log::error('Could not retrieve import data for completion event', [
                    'import_id' => $this->importId,
                ]);
            }

            return;
        }

        // Broadcast completion event with all accumulated data
        $totalRows = is_numeric($importData['total_rows'] ?? null) ? (int) $importData['total_rows'] : $this->totalRows;
        $processedRows = is_numeric($importData['processed_rows'] ?? null) ? (int) $importData['processed_rows'] : 0;
        $failedRowsCount = is_numeric($importData['failed_rows'] ?? null) ? (int) $importData['failed_rows'] : 0;
        /** @var array<int, array<string, mixed>> $failedRowsDetails */
        $failedRowsDetails = is_array($importData['failed_rows_details'] ?? null) ? $importData['failed_rows_details'] : [];
        $totalDuration = is_numeric($importData['total_duration'] ?? null) ? (float) $importData['total_duration'] : null;
        $startedAt = is_string($importData['started_at'] ?? null) ? $importData['started_at'] : null;
        $completedAt = is_string($importData['completed_at'] ?? null) ? $importData['completed_at'] : now()->toIso8601String();

        broadcast(new CsvInvitedUsersImportCompleted(
            $this->importId,
            $this->financerId,
            $this->userId,
            $totalRows,
            $processedRows,
            $failedRowsCount,
            'completed',
            null, // No global error
            $failedRowsDetails,
            $totalDuration,
            $startedAt,
            $completedAt
        ));

        Log::info('Import completed', [
            'import_id' => $this->importId,
            'total_rows' => $totalRows,
            'processed_rows' => $processedRows,
            'failed_rows' => $failedRowsCount,
            'duration_seconds' => $totalDuration,
        ]);

        // Send email with errors if there are any failed rows
        if ($failedRowsCount > 0) {
            $this->sendErrorEmailToUser(
                $failedRowsDetails,
                $totalRows,
                $processedRows,
                $failedRowsCount,
                $totalDuration
            );
        }

        // Clean up the uploaded file from appropriate storage disk
        try {
            $disk = config('filesystems.default') === 's3-local' ? 's3-local' : 's3';
            if (Storage::disk($disk)->exists($this->filePath)) {
                Storage::disk($disk)->delete($this->filePath);
                Log::info('Import file deleted', ['file_path' => $this->filePath, 'disk' => $disk]);
            }
        } catch (Exception $e) {
            Log::warning('Failed to delete import file', [
                'file_path' => $this->filePath,
                'error' => $e->getMessage(),
            ]);
        }

        // Clean up Redis tracking data
        $tracker->cleanup($this->importId);
    }

    /**
     * @param  array<int, array<string, mixed>>  $failedRowsDetails
     */
    private function sendErrorEmailToUser(
        array $failedRowsDetails,
        int $totalRows,
        int $processedRows,
        int $failedRows,
        ?float $totalDuration
    ): void {
        try {
            // Get user information
            $user = User::find($this->userId);
            if (! $user) {
                Log::warning('User not found for import error email', ['user_id' => $this->userId]);

                return;
            }

            // Get financer information
            $financer = Financer::find($this->financerId);
            $financerName = $financer?->name;

            // Send the error email
            Mail::to($user->email)->send(new CsvImportErrorsMail(
                importId: $this->importId,
                userName: $user->first_name.' '.$user->last_name,
                totalRows: $totalRows,
                processedRows: $processedRows,
                failedRows: $failedRows,
                failedRowsDetails: $failedRowsDetails,
                totalDuration: $totalDuration,
                financerName: $financerName
            ));

            Log::info('Import error email sent', [
                'import_id' => $this->importId,
                'user_email' => $user->email,
                'failed_rows_count' => $failedRows,
            ]);

            // Clean up temporary CSV attachment file if it exists
            $tempFile = storage_path('app/temp/import_errors_'.$this->importId.'.csv');
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        } catch (Exception $e) {
            Log::error('Failed to send import error email', [
                'import_id' => $this->importId,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
