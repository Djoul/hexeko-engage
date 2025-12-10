<?php

namespace App\Actions\Apideck;

use App\Events\Apideck\SIRHSyncCompletedEvent;
use App\Jobs\Apideck\CompleteSIRHSyncJob;
use App\Jobs\Apideck\ProcessSIRHBatchJob;
use App\Jobs\Apideck\StartSIRHSyncJob;
use App\Services\Apideck\ApideckService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Log;

class SyncAllEmployeesAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const BATCH_SIZE = 50;

    /**
     * @var array<string, mixed>
     */
    public array $params;

    public string $syncId;

    public string $userId;

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(array $params = [])
    {
        $this->params = $params;
        $this->syncId = Str::uuid()->toString();

        // Capture user ID at construction time
        // First check if it's in params, then check auth, finally use 'system'
        $userIdFromParams = array_key_exists('user_id', $params) && is_string($params['user_id']) ? $params['user_id'] : null;
        $authId = auth()->id();
        $this->userId = $userIdFromParams ?? (is_string($authId) || is_int($authId) ? (string) $authId : 'system');
    }

    public function handle(ApideckService $apideckService): void
    {
        try {
            Log::info('Starting employee sync job with batch processing', [
                'sync_id' => $this->syncId,
                'params' => $this->params,
            ]);

            $financerId = array_key_exists('financer_id', $this->params) && is_string($this->params['financer_id'])
                ? $this->params['financer_id']
                : null;

            // Use the userId captured at construction time
            $userId = $this->userId;

            $apideckService->initializeConsumerId($financerId);

            // Fetch all employees
            $allEmployees = $apideckService->fetchAllEmployees();
            $employees = $allEmployees['employees'] ?? [];
            $totalEmployees = count($employees);

            if (empty($employees)) {
                broadcast(new SIRHSyncCompletedEvent(
                    $this->syncId,
                    $financerId ?? '',
                    $userId,
                    0,
                    0,
                    0,
                    'completed'
                ));

                return;
            }

            // Split into batches
            $batches = array_chunk($employees, self::BATCH_SIZE);
            $totalBatches = count($batches);

            // Create batch jobs
            $jobs = [];

            // Add start job as the first job in the batch
            $jobs[] = new StartSIRHSyncJob(
                $this->syncId,
                $financerId ?? '',
                $userId,
                $totalEmployees,
                $totalBatches
            );

            // Add all processing jobs
            foreach ($batches as $index => $batchEmployees) {
                $jobs[] = new ProcessSIRHBatchJob(
                    $batchEmployees,
                    $financerId ?? '',
                    $this->syncId,
                    $index + 1,
                    $userId
                );
            }

            // Add completion job at the end of the batch
            $jobs[] = new CompleteSIRHSyncJob(
                $this->syncId,
                $financerId ?? '',
                $userId,
                $totalEmployees,
                $totalEmployees, // Will be adjusted if jobs fail
                0 // Will be adjusted if jobs fail
            );

            // Dispatch batch
            $queueName = config('queue.connections.sqs.queue');
            $queueNameStr = is_string($queueName) ? $queueName : 'default';
            Bus::chain($jobs)
//                ->name('sirh-sync-'.$this->syncId)
                ->onQueue($queueNameStr)
                ->dispatch();

        } catch (Exception $e) {
            Log::error('Employee sync job failed', [
                'sync_id' => $this->syncId,
                'error' => $e->getMessage(),
                'params' => $this->params,
            ]);

            throw $e;
        }
    }

    /**
     * Get the sync ID
     */
    public function getSyncId(): string
    {
        return $this->syncId;
    }
}
