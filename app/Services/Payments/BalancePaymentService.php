<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\CreditTypes;
use App\Events\Vouchers\VoucherPurchasedWithBalance;
use App\Exceptions\InsufficientBalanceException;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Models\CreditBalance;
use App\Models\User;
use App\Services\CreditAccountService;
use Illuminate\Support\Facades\DB;
use Log;

class BalancePaymentService
{
    public function processPayment(
        User $user,
        float $amount,
        Order $order
    ): PaymentResult {
        return DB::transaction(function () use ($user, $amount, $order): PaymentResult {
            // Lock the balance record to prevent concurrent modifications
            $balance = CreditBalance::where('owner_id', $user->id)
                ->where('owner_type', User::class)
                ->where('type', 'cash')
                ->lockForUpdate()
                ->first();

            Log::debug('Balance payment for order '.$order->id, [
                'balance' => $balance,
                'amount' => $amount,
            ]);
            // Check if balance exists and is sufficient
            if (! $balance || ! $this->hasEnoughBalance($balance, $amount)) {
                throw new InsufficientBalanceException(
                    sprintf(
                        'Insufficient balance. Required: %2f, Available: %2f',
                        $amount,
                        ($balance !== null ? $balance->balance : 0.00)
                    )
                );
            }

            // Consume the credit (convert euros to cents)
            CreditAccountService::consumeCredit(
                User::class,
                (string) $user->id,
                CreditTypes::CASH,
                (int) $amount,
                (string) $user->id,
                'voucher_purchase_order_'.$order->id
            );

            // Refresh balance to get updated value
            $balance->refresh();

            // Emit event for voucher purchase
            event(new VoucherPurchasedWithBalance($user, $order, $amount));

            return new PaymentResult(
                success: true,
                amountDebited: $amount,
                transactionId: $order->id,
                remainingBalance: $balance->balance / 100 // Convert cents to euros
            );
        });
    }

    private function hasEnoughBalance(CreditBalance $balance, float $amount): bool
    {

        return $balance->balance >= $amount;
    }
}
