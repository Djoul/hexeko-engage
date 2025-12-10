<?php

declare(strict_types=1);

namespace App\Events\Vouchers;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoucherPurchaseError implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>|null  $context
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $errorCode,
        public readonly string $errorMessage,
        public readonly ?array $context = null
    ) {}

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
        return 'voucher.purchase.error';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'errorCode' => $this->errorCode,
            'errorMessage' => $this->errorMessage,
            'context' => $this->context,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
