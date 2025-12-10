<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Events\Vouchers\VoucherPurchasedWithBalance;
use App\Exceptions\PaymentFailedException;
use App\Helpers\MoneyHelper;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Integrations\Vouchers\Amilon\Services\StripePaymentService;
use App\Models\User;
use App\Services\CreditAccountService;
use Exception;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * @deprecated
 * Mixed payments are now handled by frontend orchestration
 * 2025-01-05
 */
class MixedPaymentService
{
    public function __construct(
        private readonly BalancePaymentService $balanceService,
        private readonly StripePaymentService $stripeService
    ) {}

    public function processPayment(
        User $user,
        Order $order,
        float $balanceAmount,
        float $stripeAmount
    ): MixedPaymentResult {
        // Validate amounts
        if ($balanceAmount < 0 || $stripeAmount < 0) {
            throw new InvalidArgumentException('Invalid payment amounts');
        }

        $totalAmount = bcadd((string) $balanceAmount, (string) $stripeAmount, 2);

        // Verify total matches order amount
        // Order total_amount is already in euros, no conversion needed
        $orderAmountInEuros = $order->total_amount;
        if (bccomp($totalAmount, (string) $orderAmountInEuros, 2) !== 0) {
            throw new InvalidArgumentException(
                sprintf(
                    'Payment amounts do not match order total. Expected: %.2f, Got: %.2f',
                    $orderAmountInEuros,
                    $totalAmount
                )
            );
        }

        return DB::transaction(function () use ($user, $order, $balanceAmount, $stripeAmount): MixedPaymentResult {
            $balanceResult = null;

            try {
                // Step 1: Process balance payment
                $balanceResult = $this->balanceService->processPayment(
                    $user,
                    $balanceAmount,
                    $order
                );

                // Step 2: Process Stripe payment for remaining amount
                $product = $order->product;
                if (! $product) {
                    throw new Exception('Product not found for order');
                }

                /** @var array{payment_intent_id: string, client_secret: string, amount: float, original_amount: float, discount_percentage: float, payment_id: int} $stripePaymentData */
                $stripePaymentData = $this->stripeService->createPaymentIntent(
                    $product,
                    $stripeAmount,
                    (string) $user->id,
                    [
                        'order_id' => (string) $order->id,
                        'payment_type' => 'mixed',
                        'balance_amount' => (string) $balanceAmount,
                        'stripe_amount' => (string) $stripeAmount,
                        'total_amount' => (string) $order->total_amount,  // Already in euros
                    ]
                );

                // Emit event for the balance portion
                event(new VoucherPurchasedWithBalance($user, $order, $balanceAmount));

                return new MixedPaymentResult(
                    success: true,
                    balanceAmount: $balanceAmount,
                    stripePaymentIntentId: (string) $stripePaymentData['payment_intent_id'],
                    stripeClientSecret: (string) $stripePaymentData['client_secret'],
                    paymentMethod: 'mixed',
                    stripePaymentId: (string) $stripePaymentData['payment_id']
                );
            } catch (Exception $e) {
                // If Stripe payment fails, we need to restore the balance
                if ($balanceResult && $balanceResult->success) {
                    // Restore the consumed balance (convert euros to cents)
                    CreditAccountService::addCredit(
                        'user',
                        (string) $user->id,
                        'cash',
                        MoneyHelper::eurosToCents($balanceAmount),
                        'mixed_payment_rollback_order_'.$order->id
                    );
                }

                throw new PaymentFailedException(
                    'Mixed payment failed: '.$e->getMessage()
                );
            }
        });
    }
}
