<?php

namespace App\Jobs\Apideck;

use App\Events\Apideck\SIRHSyncStartedEvent;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StartSIRHSyncJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $syncId,
        private readonly string $financerId,
        private readonly string $userId,
        private readonly int $totalEmployees,
        private readonly int $totalBatches
    ) {}

    public function handle(): void
    {
        broadcast(new SIRHSyncStartedEvent(
            $this->syncId,
            $this->financerId,
            $this->userId,
            $this->totalEmployees,
            $this->totalBatches
        ));

        Log::info('[WebSocket] SIRH sync started', [
            'sync_id' => $this->syncId,
            'financer_id' => $this->financerId,
            'user_id' => $this->userId,
            'total_employees' => $this->totalEmployees,
            'total_batches' => $this->totalBatches,
        ]);
    }
}
