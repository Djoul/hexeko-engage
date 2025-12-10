<?php

declare(strict_types=1);

namespace App\Events\Vouchers;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoucherPurchaseNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $orderData
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $orderId,
        public readonly string $status,
        public readonly array $orderData,
        public readonly ?string $message = null
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
        return 'voucher.purchase.'.$this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'orderId' => $this->orderId,
            'status' => $this->status,
            'message' => $this->message,
            'orderData' => $this->orderData,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
