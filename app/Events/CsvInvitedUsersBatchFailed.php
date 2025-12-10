<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CsvInvitedUsersBatchFailed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $importId,
        public readonly string $financerId,
        public readonly string $userId,
        public readonly int $batchNumber,
        public readonly int $totalRows,
        public readonly string $error,
        public readonly string $exceptionClass
    ) {
        Log::error('[WebSocket] CsvInvitedUsersBatchFailed event created', [
            'import_id' => $this->importId,
            'user_id' => $this->userId,
            'batch_number' => $this->batchNumber,
            'total_rows' => $this->totalRows,
            'error' => $this->error,
            'exception_class' => $this->exceptionClass,
            'channel' => 'private-user.'.$this->userId,
        ]);
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'batch.failed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $data = [
            'import_id' => $this->importId,
            'financer_id' => $this->financerId,
            'batch_number' => $this->batchNumber,
            'total_rows' => $this->totalRows,
            'error' => $this->error,
            'exception_class' => $this->exceptionClass,
            'failed_at' => now()->toIso8601String(),
        ];

        Log::error('[WebSocket] Broadcasting BatchFailed event', [
            'event' => 'batch.failed',
            'channel' => 'private-user.'.$this->userId,
            'data' => $data,
        ]);

        return $data;
    }
}
