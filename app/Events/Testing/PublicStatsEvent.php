<?php

namespace App\Events\Testing;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PublicStatsEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $stats
     */
    public function __construct(
        public array $stats,
        public string $period = 'daily'
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('public-stats'),
            new Channel('dashboard'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'stats.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'stats' => $this->stats,
            'period' => $this->period,
            'timestamp' => now()->toISOString(),
        ];
    }
}
