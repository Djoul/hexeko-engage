<?php

namespace App\Events\Apideck;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TotalEmployeesCalculationFailedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $financerId,
        public string $userId,
        public string $error
    ) {
        Log::info('[WebSocket] TotalEmployeesCalculationFailed event created', [
            'financer_id' => $this->financerId,
            'user_id' => $this->userId,
            'error' => $this->error,
            'channel' => 'private-user.'.$this->userId,
        ]);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'sirh.total-employees.failed';
    }

    public function broadcastWith(): array
    {
        return [
            'financer_id' => $this->financerId,
            'error' => $this->error,
            'retry_after' => 60,
        ];
    }
}
