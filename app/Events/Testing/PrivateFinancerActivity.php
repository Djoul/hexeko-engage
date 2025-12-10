<?php

namespace App\Events\Testing;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrivateFinancerActivity implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $financerId,
        public string $activityType,
        /** @var array<string, mixed> */
        public array $details,
        public ?string $userId = null
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('financer.'.$this->financerId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'activity.'.$this->activityType;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'financer_id' => $this->financerId,
            'activity_type' => $this->activityType,
            'details' => $this->details,
            'user_id' => $this->userId,
            'timestamp' => now()->toISOString(),
        ];
    }
}
