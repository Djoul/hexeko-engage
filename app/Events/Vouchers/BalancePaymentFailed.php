<?php

declare(strict_types=1);

namespace App\Events\Vouchers;

use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BalancePaymentFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Order $order,
        public readonly float $amount,
        public readonly string $reason
    ) {}
}
