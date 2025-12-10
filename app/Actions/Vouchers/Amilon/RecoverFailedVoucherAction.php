<?php

declare(strict_types=1);

namespace App\Actions\Vouchers\Amilon;

use App\Enums\IDP\RoleDefaults;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Integrations\Vouchers\Amilon\Services\VoucherRecoveryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RecoverFailedVoucherAction
{
    public function __construct(
        private readonly VoucherRecoveryService $recoveryService
    ) {}

    public function execute(string $orderId): Order
    {
        $user = Auth::user();

        // Admin users can retry any order
        if ($user && $user->hasRole(RoleDefaults::FINANCER_SUPER_ADMIN)) {
            $order = Order::findOrFail($orderId);
        } else {
            // Regular users can only retry their own orders
            $order = Order::where('id', $orderId)
                ->where('user_id', Auth::id())
                ->firstOrFail();
        }

        if (! $this->recoveryService->canRetry($order)) {
            throw new RuntimeException('Order cannot be retried');
        }

        Log::info('Manual recovery initiated for order', [
            'order_id' => $orderId,
            'user_id' => Auth::id(),
            'is_admin' => $user && $user->hasRole(RoleDefaults::FINANCER_SUPER_ADMIN),
        ]);

        $result = $this->recoveryService->attemptRecovery($order);

        if (! $result->success) {
            Log::info('Manual recovery failed', [
                'order_id' => $orderId,
                'attempts' => $order->refresh()->recovery_attempts,
                'status' => $result->newStatus,
            ]);
        } else {
            Log::info('Manual recovery successful', [
                'order_id' => $orderId,
            ]);
        }

        $order->refresh();

        return $order;
    }
}
