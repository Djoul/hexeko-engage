<?php

namespace App\Events\Testing;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestChannelBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $channel,
        public readonly string $eventName,
        public readonly array $data = []
    ) {}

    public function broadcastOn(): Channel|PrivateChannel
    {
        if (str_starts_with($this->channel, 'private-')) {
            return new PrivateChannel(substr($this->channel, 8));
        }

        if (str_starts_with($this->channel, 'public-')) {
            return new Channel(substr($this->channel, 7));
        }

        return new Channel($this->channel);
    }

    public function broadcastAs(): string
    {
        return $this->eventName;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->data;
    }
}
