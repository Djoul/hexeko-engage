<?php

namespace App\Jobs\Apideck;

use App\Events\Apideck\SIRHBatchProcessedEvent;
use App\Services\Apideck\ApideckService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;
use Throwable;

class ProcessSIRHBatchJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $employees
     */
    public function __construct(
        public readonly array $employees,
        public readonly string $financerId,
        public readonly string $syncId,
        public readonly int $batchNumber,
        public readonly string $userId
    ) {}

    public function handle(ApideckService $apideckService): void
    {
        Log::info('Processing SIRH batch', [
            'sync_id' => $this->syncId,
            'batch_number' => $this->batchNumber,
            'employee_count' => count($this->employees),
        ]);

        $processedCount = 0;
        $failedCount = 0;
        $failedRows = [];

        // Initialize Apideck with financer context
        $apideckService->initializeConsumerId($this->financerId);

        foreach ($this->employees as $employee) {
            try {
                // Use existing syncEmployee method
                $result = $apideckService->syncEmployee($employee, $this->financerId);

                if ($result !== false) {
                    $processedCount++;
                } else {
                    $failedCount++;
                    $failedRows[] = [
                        'row' => $employee,
                        'error' => 'Failed to sync employee',
                    ];
                }
            } catch (Throwable $e) {
                $failedCount++;
                $failedRows[] = [
                    'row' => $employee,
                    'error' => $e->getMessage(),
                ];

                Log::warning('Failed to process employee in batch', [
                    'sync_id' => $this->syncId,
                    'batch_number' => $this->batchNumber,
                    'employee_id' => $employee['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Broadcast batch processed event
        broadcast(new SIRHBatchProcessedEvent(
            $this->syncId,
            $this->financerId,
            $this->userId,
            $this->batchNumber,
            $processedCount,
            $failedCount,
            $failedRows
        ));

        Log::info('SIRH batch processed', [
            'sync_id' => $this->syncId,
            'batch_number' => $this->batchNumber,
            'processed' => $processedCount,
            'failed' => $failedCount,
        ]);
    }

    /**
     * Handle job failure
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Batch processing failed', [
            'sync_id' => $this->syncId,
            'batch_number' => $this->batchNumber,
            'error' => $exception->getMessage(),
        ]);

        $failedRows = array_map(function (array $employee) use ($exception): array {
            return [
                'row' => $employee,
                'error' => 'Batch failed: '.$exception->getMessage(),
            ];
        }, $this->employees);

        broadcast(new SIRHBatchProcessedEvent(
            $this->syncId,
            $this->financerId,
            $this->userId,
            $this->batchNumber,
            0,
            count($this->employees),
            $failedRows
        ));
    }
}
