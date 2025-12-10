<?php

namespace App\Jobs\Apideck;

use App\Events\Apideck\TotalEmployeesCalculatedEvent;
use App\Events\Apideck\TotalEmployeesCalculationFailedEvent;
use App\Services\Apideck\ApideckService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class GetTotalEmployeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public string $financerId,
        public string $consumerId,
        public string $userId
    ) {}

    public function handle(ApideckService $service): void
    {
        try {
            $totalEmployees = $service->calculateTotalEmployees($this->financerId);

            broadcast(new TotalEmployeesCalculatedEvent(
                $this->financerId,
                $this->consumerId,
                $this->userId,
                $totalEmployees
            ));

            Cache::forget($service->totalEmployeesJobLockKey($this->financerId, $this->consumerId));

            Log::info('Total employees calculated asynchronously', [
                'financer_id' => $this->financerId,
                'total_employees' => $totalEmployees,
            ]);
        } catch (Throwable $exception) {
            Log::error('Failed to calculate total employees', [
                'financer_id' => $this->financerId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $service = app(ApideckService::class);
        Cache::forget($service->totalEmployeesJobLockKey($this->financerId, $this->consumerId));

        broadcast(new TotalEmployeesCalculationFailedEvent(
            $this->financerId,
            $this->userId,
            $exception->getMessage()
        ));

        Log::error('Total employees calculation failed permanently', [
            'financer_id' => $this->financerId,
            'error' => $exception->getMessage(),
        ]);
    }
}
