<?php

namespace App\Events\Apideck;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SIRHSyncStartedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $syncId,
        public readonly string $financerId,
        public readonly string $userId,
        public readonly int $totalEmployees,
        public readonly int $totalBatches
    ) {
        Log::info('[WebSocket] SIRHSyncStartedEvent created', [
            'sync_id' => $this->syncId,
            'user_id' => $this->userId,
            'financer_id' => $this->financerId,
            'total_employees' => $this->totalEmployees,
            'total_batches' => $this->totalBatches,
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
        return 'sirh.sync.started';
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
            'financer_id' => $this->financerId,
            'total_employees' => $this->totalEmployees,
            'total_batches' => $this->totalBatches,
            'started_at' => now()->toIso8601String(),
        ];

        Log::info('[WebSocket] Broadcasting SIRHSyncStarted event', [
            'event' => 'sirh.sync.started',
            'channel' => 'private-user.'.$this->userId,
            'data' => $data,
        ]);

        return $data;
    }
}
