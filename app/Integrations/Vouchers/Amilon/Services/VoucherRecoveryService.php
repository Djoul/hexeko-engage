<?php

declare(strict_types=1);

namespace App\Integrations\Vouchers\Amilon\Services;

use App\Events\Vouchers\VoucherPurchaseNotification;
use App\Integrations\Vouchers\Amilon\DTO\RecoveryResult;
use App\Integrations\Vouchers\Amilon\Enums\OrderStatus;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Models\User;
use App\Services\Payments\BalancePaymentService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class VoucherRecoveryService
{
    private const MAX_RETRY_ATTEMPTS = 3;

    public function __construct(
        private readonly AmilonOrderService $orderService,
        private readonly BalancePaymentService $balanceService
    ) {}

    public function identifyFailedOrders(): Collection
    {
        // Identify failed orders eligible for recovery (ERROR status only, no age limit)
        return Order::where('status', OrderStatus::ERROR)
            ->where('recovery_attempts', '<', self::MAX_RETRY_ATTEMPTS)
            ->get();
    }

    public function attemptRecovery(Order $order): RecoveryResult
    {
        Log::info('Attempting manual recovery for order', [
            'order_id' => $order->id,
            'attempt' => $order->recovery_attempts + 1,
        ]);

        $order->increment('recovery_attempts');
        $order->update([
            'status' => OrderStatus::PENDING,  // Mark as pending during recovery
            'last_recovery_attempt' => Carbon::now(),
        ]);

        try {
            // Load relationships
            $order->load(['product', 'user']);

            // Attempt to create the order in Amilon API
            $amilonOrder = $this->orderService->createOrder(
                product: $order->product,
                quantity: 1, // Single voucher purchase
                externalOrderId: $order->external_order_id,
                userId: $order->user_id ? (string) $order->user_id : null
            );

            // Success! Apply the same logic as /purchase success
            // 1. Update order status to CONFIRMED
            $order->update([
                'status' => OrderStatus::CONFIRMED,
                'last_error' => null,
            ]);

            // 2. Debit user balance for the order amount
            $user = User::find($order->user_id);
            if ($user && $order->amount > 0) {
                try {
                    $paymentResult = $this->balanceService->processPayment(
                        $user,
                        (int) $order->amount,
                        $order
                    );

                    // Update order with balance amount used
                    $order->update([
                        'balance_amount_used' => $order->amount,
                    ]);

                    // Broadcast success notification
                    broadcast(new VoucherPurchaseNotification(
                        userId: (string) $user->id,
                        orderId: (string) $order->id,
                        status: 'recovery_completed',
                        orderData: [
                            'product_name' => $order->product->name ?? '',
                            'amount_debited' => $order->amount,
                            'recovery_attempt' => $order->recovery_attempts,
                        ],
                        message: 'Your voucher order has been successfully recovered'
                    ));

                    Log::info('Order recovery successful with balance debit', [
                        'order_id' => $order->id,
                        'amount_debited' => $order->amount,
                    ]);
                } catch (Exception $balanceError) {
                    // Log the balance error but don't fail the recovery
                    Log::error('Failed to debit balance during recovery', [
                        'order_id' => $order->id,
                        'error' => $balanceError->getMessage(),
                    ]);
                }
            }

            return RecoveryResult::success(
                'Order successfully recovered',
                OrderStatus::CONFIRMED
            );

        } catch (Exception $e) {
            Log::error('Order recovery failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'attempts' => $order->recovery_attempts,
            ]);

            // After 3 attempts, mark as CANCELLED instead of ERROR
            $finalStatus = $order->recovery_attempts >= self::MAX_RETRY_ATTEMPTS
                ? OrderStatus::CANCELLED
                : OrderStatus::ERROR;

            $order->update([
                'status' => $finalStatus,
                'last_error' => $e->getMessage(),
            ]);

            if ($finalStatus === OrderStatus::CANCELLED) {
                Log::warning('Order permanently cancelled after max recovery attempts', [
                    'order_id' => $order->id,
                    'attempts' => $order->recovery_attempts,
                ]);
            }

            return RecoveryResult::failure(
                'Recovery failed: '.$e->getMessage(),
                $finalStatus,
                $e->getMessage()
            );
        }
    }

    public function canRetry(Order $order): bool
    {
        // Only ERROR status orders can be retried, up to 3 attempts
        return $order->status === OrderStatus::ERROR
            && $order->recovery_attempts < self::MAX_RETRY_ATTEMPTS;
    }
}
