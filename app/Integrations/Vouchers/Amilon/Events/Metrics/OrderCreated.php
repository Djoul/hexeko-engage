<?php

namespace App\Integrations\Vouchers\Amilon\Events\Metrics;

use App\Integrations\Vouchers\Amilon\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 *@deprecated
 */
class OrderCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly string $userId,
        public readonly Order $order
    ) {}
}
