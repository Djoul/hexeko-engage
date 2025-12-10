<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CsvInvitedUsersImportStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $importId,
        public readonly string $financerId,
        public readonly string $userId,
        public readonly int $totalRows,
        public readonly int $totalBatches
    ) {
        Log::info('[WebSocket] CsvInvitedUsersImportStarted event created', [
            'import_id' => $this->importId,
            'user_id' => $this->userId,
            'total_rows' => $this->totalRows,
            'total_batches' => $this->totalBatches,
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
        return 'import.started';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $data = [
            'import_id' => $this->importId,
            'financer_id' => $this->financerId,
            'total_rows' => $this->totalRows,
            'total_batches' => $this->totalBatches,
            'started_at' => now()->toIso8601String(),
        ];

        Log::info('[WebSocket] Broadcasting ImportStarted event', [
            'event' => 'import.started',
            'channel' => 'private-user.'.$this->userId,
            'data' => $data,
        ]);

        return $data;
    }
}
