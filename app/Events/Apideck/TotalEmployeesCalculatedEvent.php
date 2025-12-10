<?php

namespace App\Events\Apideck;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

class TotalEmployeesCalculatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $financerId,
        public string $consumerId,
        public string $userId,
        public int $totalEmployees
    ) {
        Log::info('[WebSocket] TotalEmployeesCalculated event created', [
            'financer_id' => $this->financerId,
            'consumer_id' => $this->consumerId,
            'user_id' => $this->userId,
            'total_employees' => $this->totalEmployees,
            'channel' => 'private-user.'.$this->userId,
        ]);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'sirh.total-employees.calculated';
    }

    public function broadcastWith(): array
    {
        $data = [
            'financer_id' => $this->financerId,
            'consumer_id' => $this->consumerId,
            'total_employees' => $this->totalEmployees,
            'cached_until' => Date::now()->addHour()->toIso8601String(),
        ];

        Log::info('[WebSocket] Broadcasting TotalEmployeesCalculated event', [
            'event' => 'sirh.total-employees.calculated',
            'channel' => 'private-user.'.$this->userId,
            'data' => $data,
        ]);

        return $data;
    }
}
