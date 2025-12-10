<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CsvInvitedUsersBatchProcessed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $importId,
        public readonly string $financerId,
        public readonly string $userId,
        public readonly int $batchNumber,
        public readonly int $processedCount,
        public readonly int $failedCount,
        /** @var array<int, array<string, mixed>> */
        public readonly array $failedRows = []
    ) {
        Log::info('[WebSocket] CsvInvitedUsersBatchProcessed event created', [
            'import_id' => $this->importId,
            'user_id' => $this->userId,
            'batch_number' => $this->batchNumber,
            'processed_count' => $this->processedCount,
            'failed_count' => $this->failedCount,
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
        return 'batch.processed';
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
            'processed_count' => $this->processedCount,
            'failed_count' => $this->failedCount,
            'failed_rows' => $this->failedRows,
            'processed_at' => now()->toIso8601String(),
        ];

        Log::info('[WebSocket] Broadcasting BatchProcessed event', [
            'event' => 'batch.processed',
            'channel' => 'private-user.'.$this->userId,
            'data' => $data,
        ]);

        return $data;
    }
}
