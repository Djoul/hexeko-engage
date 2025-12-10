<?php

namespace App\Services;

use App\Jobs\HandleCsvImportBatchCompletionJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CsvImportTrackerService
{
    private const KEY_PREFIX = 'csv_import:';

    private const TTL = 3600; // 1 hour

    /**
     * Initialize a new import tracking using Redis hash and atomic counters
     */
    public function initializeImport(string $importId, int $totalRows, int $totalBatches): void
    {
        // Use hash tags to ensure all keys hash to the same slot in Redis Cluster
        // The {importId} part ensures all keys with the same importId go to the same slot
        $hashKey = self::KEY_PREFIX.'{'.$importId.'}:info';
        $counterKey = self::KEY_PREFIX.'{'.$importId.'}:counters';
        $failedKey = self::KEY_PREFIX.'{'.$importId.'}:failed';

        // Use Redis pipeline for atomic operations
        /** @phpstan-ignore-next-line */
        Redis::pipeline(function ($pipe) use ($hashKey, $counterKey, $failedKey, $importId, $totalRows, $totalBatches): void {
            // Store static info in hash
            $pipe->hMSet($hashKey, [
                'import_id' => $importId,
                'total_rows' => $totalRows,
                'total_batches' => $totalBatches,
                'started_at' => now()->toIso8601String(),
            ]);

            // Initialize counters
            $pipe->hMSet($counterKey, [
                'processed_batches' => 0,
                'processed_rows' => 0,
                'failed_rows' => 0,
            ]);

            // Set TTL on all keys
            $pipe->expire($hashKey, self::TTL);
            $pipe->expire($counterKey, self::TTL);
            $pipe->expire($failedKey, self::TTL);
        });

        Log::info('CSV import tracking initialized', [
            'import_id' => $importId,
            'total_rows' => $totalRows,
            'total_batches' => $totalBatches,
        ]);
    }

    /**
     * Update import progress atomically after a batch is processed
     *
     * @param  array<int, mixed>  $failedRows
     */
    public function updateBatchProgress(
        string $importId,
        int $processedCount,
        int $failedCount,
        array $failedRows = []
    ): void {
        // Use hash tags to ensure all keys hash to the same slot in Redis Cluster
        $counterKey = self::KEY_PREFIX.'{'.$importId.'}:counters';
        $failedKey = self::KEY_PREFIX.'{'.$importId.'}:failed';

        // Check if tracking exists (for tests that don't initialize tracking)
        if (! Redis::exists($counterKey)) {
            Log::debug('Import tracking not initialized, skipping update', [
                'import_id' => $importId,
            ]);

            return;
        }

        // Use Redis pipeline for atomic updates
        /** @phpstan-ignore-next-line */
        $results = Redis::pipeline(function ($pipe) use ($counterKey, $failedKey, $processedCount, $failedCount, $failedRows): void {
            // Atomically increment counters
            $pipe->hIncrBy($counterKey, 'processed_batches', 1);
            $pipe->hIncrBy($counterKey, 'processed_rows', $processedCount);
            $pipe->hIncrBy($counterKey, 'failed_rows', $failedCount);

            // Add failed rows to list (if any)
            foreach ($failedRows as $failedRow) {
                $pipe->rPush($failedKey, json_encode($failedRow));
            }

            // Get current batch count to check if completed
            $pipe->hGet($counterKey, 'processed_batches');
        });

        // Get the last result which should be the processed_batches count
        $currentBatches = 0;
        if (is_array($results) && $results !== []) {
            $lastResult = $results[count($results) - 1];
            $currentBatches = is_numeric($lastResult) ? (int) $lastResult : 0;
        }

        // Check if all batches are completed
        $hashKey = self::KEY_PREFIX.'{'.$importId.'}:info';
        $totalBatches = Redis::hGet($hashKey, 'total_batches');
        $totalBatchesInt = is_numeric($totalBatches) ? (int) $totalBatches : 0;

        // Only trigger completion if we have tracking data
        if ($totalBatchesInt > 0 && $currentBatches >= $totalBatchesInt) {
            Redis::hSet($hashKey, 'completed_at', now()->toIso8601String());

            // Trigger the completion event
            $this->triggerCompletionEvent($importId);
        }

        Log::info('CSV import batch progress updated', [
            'import_id' => $importId,
            'current_batches' => $currentBatches,
            'total_batches' => $totalBatches,
            'processed_rows' => $processedCount,
            'failed_rows' => $failedCount,
        ]);
    }

    /**
     * Trigger completion event with all accumulated data
     */
    private function triggerCompletionEvent(string $importId): void
    {
        $data = $this->getCompleteImportData($importId);

        if ($data === null || $data === []) {
            Log::error('Could not retrieve import data for completion', ['import_id' => $importId]);

            return;
        }

        // Dispatch the completion job to send the final event
        $financerId = array_key_exists('financer_id', $data) && is_scalar($data['financer_id']) ? (string) $data['financer_id'] : '';
        $userId = array_key_exists('user_id', $data) && is_scalar($data['user_id']) ? (string) $data['user_id'] : '';
        $filePath = array_key_exists('file_path', $data) && is_scalar($data['file_path']) ? (string) $data['file_path'] : '';
        $totalRows = array_key_exists('total_rows', $data) && is_numeric($data['total_rows']) ? (int) $data['total_rows'] : 0;

        HandleCsvImportBatchCompletionJob::dispatch(
            $importId,
            $financerId,
            $userId,
            $filePath,
            $totalRows
        )->delay(now()->addSeconds(2)); // Small delay to ensure all batches are processed
    }

    /**
     * Get complete import tracking data from Redis
     *
     * @return array<string, mixed>|null
     */
    public function getCompleteImportData(string $importId): ?array
    {
        // Use hash tags to ensure all keys hash to the same slot in Redis Cluster
        $hashKey = self::KEY_PREFIX.'{'.$importId.'}:info';
        $counterKey = self::KEY_PREFIX.'{'.$importId.'}:counters';
        $failedKey = self::KEY_PREFIX.'{'.$importId.'}:failed';

        // Get all data atomically
        /** @phpstan-ignore-next-line */
        $results = Redis::pipeline(function ($pipe) use ($hashKey, $counterKey, $failedKey): void {
            $pipe->hGetAll($hashKey);
            $pipe->hGetAll($counterKey);
            $pipe->lRange($failedKey, 0, -1);
        });

        if (empty($results[0])) {
            return null;
        }

        $info = $results[0];
        $counters = $results[1];
        $failedRowsJson = $results[2] ?? [];

        // Parse failed rows
        $failedRows = array_map(function ($json) {
            return json_decode($json, true);
        }, $failedRowsJson);

        // Calculate duration
        $duration = null;
        if (array_key_exists('started_at', $info)) {
            $startTime = Carbon::parse($info['started_at']);
            $endTime = array_key_exists('completed_at', $info)
                ? Carbon::parse($info['completed_at'])
                : now();
            $duration = $startTime->diffInSeconds($endTime);
        }

        return array_merge($info, $counters, [
            'failed_rows_details' => $failedRows,
            'total_duration' => $duration,
        ]);
    }

    /**
     * Store additional import metadata (financer_id, user_id, file_path)
     */
    public function storeImportMetadata(string $importId, string $financerId, string $userId, string $filePath): void
    {
        // Use hash tags to ensure all keys hash to the same slot in Redis Cluster
        $hashKey = self::KEY_PREFIX.'{'.$importId.'}:info';

        Redis::hMSet($hashKey, [
            'financer_id' => $financerId,
            'user_id' => $userId,
            'file_path' => $filePath,
        ]);
    }

    /**
     * Clean up all import tracking data from Redis
     */
    public function cleanup(string $importId): void
    {
        // Use hash tags to ensure all keys hash to the same slot in Redis Cluster
        $keys = [
            self::KEY_PREFIX.'{'.$importId.'}:info',
            self::KEY_PREFIX.'{'.$importId.'}:counters',
            self::KEY_PREFIX.'{'.$importId.'}:failed',
        ];

        // Delete keys individually to avoid CROSSSLOT error in Redis Cluster
        foreach ($keys as $key) {
            Redis::del($key);
        }

        Log::info('CSV import tracking data cleaned up', ['import_id' => $importId]);
    }
}
