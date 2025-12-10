<?php

namespace App\Actions\User\InvitedUser;

use App\Events\CsvInvitedUsersImportCompleted;
use App\Events\CsvInvitedUsersImportStarted;
use App\Jobs\HandleCsvImportBatchCompletionJob;
use App\Jobs\ProcessCsvInvitedUsersBatchJob;
use App\Services\CsvImportTrackerService;
use App\Services\FileReaders\FileReaderFactory;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Log;
use Throwable;

class ImportInvitedUsersFromFileAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const BATCH_SIZE = 50;

    private const REQUIRED_HEADERS = ['first_name', 'last_name', 'email'];

    private const OPTIONAL_HEADERS = ['phone', 'external_id'];

    public function __construct(
        public readonly string $filePath,
        public readonly string $financerId,
        public readonly string $userId,
        public readonly ?string $importId = null
    ) {}

    public function handle(): string
    {
        $importId = $this->importId ?? Str::uuid()->toString();

        try {
            Log::info('Starting file import', [
                'import_id' => $importId,
                'file_path' => $this->filePath,
                'financer_id' => $this->financerId,
            ]);

            // Read and validate file using FileReaderFactory
            $fileData = $this->readAndValidateFile();

            Log::info('File data read', [
                'import_id' => $importId,
                'rows_count' => count($fileData['rows'] ?? []),
                'error' => $fileData['error'] ?? null,
            ]);

            if ($fileData['error']) {
                $this->dispatchFailedEvent($importId, $fileData['error']);

                return $importId;
            }

            $totalRows = count($fileData['rows']);

            // Handle empty file
            if ($totalRows === 0) {
                CsvInvitedUsersImportCompleted::dispatch(
                    $importId,
                    $this->financerId,
                    $this->userId,
                    0,
                    0,
                    0,
                    'completed'
                );

                return $importId;
            }

            // Chunk the rows into batches
            $batches = array_chunk($fileData['rows'], self::BATCH_SIZE);
            $totalBatches = count($batches);

            // Initialize tracking in Redis
            $tracker = app(CsvImportTrackerService::class);
            $tracker->initializeImport($importId, $totalRows, $totalBatches);
            $tracker->storeImportMetadata($importId, $this->financerId, $this->userId, $this->filePath);

            // Broadcast start event immediately (uses ShouldBroadcastNow)
            broadcast(new CsvInvitedUsersImportStarted(
                $importId,
                $this->financerId,
                $this->userId,
                $totalRows,
                $totalBatches
            ));

            // Dispatch individual jobs instead of batch to avoid serialization issues
            foreach ($batches as $index => $batchRows) {
                Log::info('Dispatching batch job', [
                    'import_id' => $importId,
                    'batch_number' => $index + 1,
                    'rows_in_batch' => count($batchRows),
                ]);

                ProcessCsvInvitedUsersBatchJob::dispatch(
                    $batchRows,
                    $this->financerId,
                    $this->userId,
                    $importId,
                    $index + 1 // Batch number starts at 1
                );
            }

            // Schedule completion check job with delay to ensure all batches are processed
            $estimatedProcessingTime = max(30, $totalBatches * 10); // seconds
            HandleCsvImportBatchCompletionJob::dispatch(
                $importId,
                $this->financerId,
                $this->userId,
                $this->filePath,
                $totalRows
            )->delay(now()->addSeconds($estimatedProcessingTime));

            Log::info('File import jobs dispatched', [
                'import_id' => $importId,
                'total_rows' => $totalRows,
                'total_batches' => $totalBatches,
            ]);

            return $importId;

        } catch (Throwable $e) {
            Log::error('Failed to process file import', [
                'import_id' => $importId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->dispatchFailedEvent($importId, $e->getMessage());

            // Don't rethrow to prevent job from being retried
            return $importId;
        }
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, error: string|null}
     */
    private function readAndValidateFile(): array
    {
        try {
            Log::info('Reading file', ['file_path' => $this->filePath]);

            // Use s3-local for local/testing environments, s3 for production
            $disk = app()->environment(['local', 'testing']) ? 's3-local' : 's3';

            // Check if file exists using Storage facade
            if (! Storage::disk($disk)->exists($this->filePath)) {
                Log::error('File not found', [
                    'file_path' => $this->filePath,
                    'disk' => $disk,
                    'all_files' => Storage::disk($disk)->allFiles(),
                ]);

                return ['rows' => [], 'error' => 'File not found'];
            }

            // Detect file type and create appropriate reader
            $factory = new FileReaderFactory;
            $extension = strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION));

            try {
                $reader = $factory->createFromFile($this->filePath);
            } catch (InvalidArgumentException $e) {
                return ['rows' => [], 'error' => $e->getMessage()];
            }

            // Read and validate file (FileReaders use Storage facade internally)
            $result = $reader->readAndValidate($this->filePath);

            if ($result['error'] !== null) {
                return $result;
            }

            // Ensure all required and optional fields exist in each row
            $rows = array_map(function (array $row): array {
                foreach (self::REQUIRED_HEADERS as $header) {
                    if (! array_key_exists($header, $row)) {
                        $row[$header] = '';
                    }
                }

                foreach (self::OPTIONAL_HEADERS as $header) {
                    if (! array_key_exists($header, $row)) {
                        $row[$header] = '';
                    }
                }

                return $row;
            }, $result['rows']);

            return ['rows' => $rows, 'error' => null];

        } catch (Exception $e) {
            Log::error('Failed to read file', [
                'error' => $e->getMessage(),
                'file_path' => $this->filePath,
            ]);

            return ['rows' => [], 'error' => 'Failed to read file: '.$e->getMessage()];
        }
    }

    private function dispatchFailedEvent(string $importId, string $error): void
    {
        CsvInvitedUsersImportCompleted::dispatch(
            $importId,
            $this->financerId,
            $this->userId,
            0,
            0,
            0,
            'failed',
            $error
        );
    }
}
