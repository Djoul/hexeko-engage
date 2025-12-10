<?php

namespace App\Events\Apideck;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SIRHBatchProcessedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, array{row: array<string, mixed>, error: string}>  $failedRows
     */
    public function __construct(
        public readonly string $syncId,
        public readonly string $financerId,
        public readonly string $userId,
        public readonly int $batchNumber,
        public readonly int $processedCount,
        public readonly int $failedCount,
        public readonly array $failedRows
    ) {
        Log::info('[WebSocket] SIRHBatchProcessedEvent created', [
            'sync_id' => $this->syncId,
            'user_id' => $this->userId,
            'financer_id' => $this->financerId,
            'batch_number' => $this->batchNumber,
            'processed_count' => $this->processedCount,
            'failed_count' => $this->failedCount,
            'channel' => 'private-user.'.$this->userId,
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'sirh.batch.processed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $data = [
            'sync_id' => $this->syncId,
            'batch_number' => $this->batchNumber,
            'processed_count' => $this->processedCount,
            'failed_count' => $this->failedCount,
            'failed_rows' => $this->failedRows,
            'processed_at' => now()->toIso8601String(),
        ];

        Log::info('[WebSocket] Broadcasting SIRHBatchProcessed event', [
            'event' => 'sirh.batch.processed',
            'channel' => 'private-user.'.$this->userId,
            'data' => $data,
        ]);

        return $data;
    }
}
