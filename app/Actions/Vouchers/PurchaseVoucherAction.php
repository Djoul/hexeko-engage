<?php

declare(strict_types=1);

namespace App\Actions\Vouchers;

use App\Enums\CreditTypes;
use App\Events\Vouchers\VoucherPurchaseError;
use App\Events\Vouchers\VoucherPurchaseNotification;
use App\Integrations\Vouchers\Amilon\Enums\OrderStatus;
use App\Integrations\Vouchers\Amilon\Exceptions\AmilonOrderErrorException;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Integrations\Vouchers\Amilon\Models\Product;
use App\Integrations\Vouchers\Amilon\Services\AmilonOrderService;
use App\Models\User;
use App\Services\CreditAccountService;
use App\Services\Payments\BalancePaymentService;
use App\Services\Payments\PaymentResult;
use Exception;
use Log;

/**
 *  only supports balance payments. Support for Stripe and mixed payments
 * needs to be implemented by integrating with StripePaymentService.
 */
class PurchaseVoucherAction
{
    public function __construct(
        private readonly BalancePaymentService $balanceService,
        private readonly AmilonOrderService $amilonService
    ) {}

    /**
     * Purchase a voucher using credit balance
     *
     * @param array{
     *   product_id: string,
     *   payment_method: string,
     *   stripe_payment_id?: string,
     *   balance_amount?: int,
     *   order_recovered_id?: string
     * } $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            throw new Exception('User not authenticated');
        }

        $product = Product::find($data['product_id']);

        if (! $product) {
            throw new Exception('Product not found');
        }

        // Validate product availability
        if (! $product->is_available) {
            return [
                'success' => false,
                'message' => 'Product is not available for purchase',
                'payment_method' => $data['payment_method'] ?? 'stripe',
            ];
        }

        $order = null;
        $paymentResult = null;
        $amountDebited = 0;

        try {
            // Create the order with initial PENDING status (outside transaction)
            $order = Order::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'merchant_id' => $product->merchant_id,
                'external_order_id' => 'ENGAGE-'.now()->format('YmdHis').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                'amount' => (int) $product->net_price,
                'total_amount' => (int) $product->price,  // Use full price for total
                'payment_method' => $data['payment_method'],
                'stripe_payment_id' => $data['stripe_payment_id'] ?? null,
                'balance_amount_used' => $data['balance_amount'] ?? null,
                'status' => OrderStatus::PENDING,
                'order_recovered_id' => $data['order_recovered_id'] ?? null,  // Reference to cancelled order being recovered
            ]);

            // Load the product relationship
            $order->load('product');

            // Log if this is a recovery of a cancelled order
            if (array_key_exists('order_recovered_id', $data)) {
                Log::info('Creating new order to recover cancelled order', [
                    'new_order_id' => $order->id,
                    'recovered_order_id' => $data['order_recovered_id'],
                    'user_id' => $user->id,
                ]);
            }

            // Broadcast order creation notification
            broadcast(new VoucherPurchaseNotification(
                userId: (string) $user->id,
                orderId: $order ? (string) $order->id : '',
                status: 'created',
                orderData: [
                    'product_name' => $product->name ?? '',
                    'amount_cents' => $product->net_price,  // Send amount in cents
                    'merchant_id' => $product->merchant_id,
                    'payment_method' => $data['payment_method'],
                    'is_recovery' => array_key_exists('order_recovered_id', $data),
                ],
                message: array_key_exists('order_recovered_id', $data)
                    ? 'Recovering your cancelled voucher order'
                    : 'Your voucher order has been created'
            ));

            Log::debug('purchase voucher action', [
                'net_price' => $product->net_price,
            ]);

            // Process balance payment
            $amountDebited = (int) $product->net_price;
            $paymentResult = $this->processBalancePayment($user, $order, $amountDebited);

            // If balance payment succeeded, create the Amilon order
            $quantity = 1; // Single voucher purchase
            $amilonOrder = $this->amilonService->createOrder(
                product: $product,
                quantity: $quantity,
                externalOrderId: $order->external_order_id,
                userId: (string) $user->id
            );

            // Update order status to CONFIRMED after successful Amilon creation
            $order->update(['status' => OrderStatus::CONFIRMED]);

            // Combine both results
            $paymentResult['amilon_order'] = $amilonOrder;

            return $paymentResult;

        } catch (AmilonOrderErrorException $e) {
            // Specific handling for Amilon API errors
            Log::error('Amilon API error during voucher purchase', [
                'error' => $e->getMessage(),
                'order_id' => $order !== null ? $order->id : null,
                'user_id' => $user->id,
            ]);

            // Restore user balance if payment was processed
            if ($amountDebited > 0) {
                $this->restoreUserBalance($user, $amountDebited, $order);
            }

            if ($order) {
                // Update order status to ERROR
                $order->update([
                    'status' => OrderStatus::ERROR,
                    'last_error' => $e->getMessage(),
                    'balance_amount_used' => 0, // Reset balance used since we restored it
                ]);
            }

            // Broadcast error notification
            broadcast(new VoucherPurchaseError(
                userId: (string) $user->id,
                errorCode: 'AMILON_API_ERROR',
                errorMessage: 'Amilon API error: '.$e->getMessage(),
                context: [
                    'product_id' => $product->id,
                    'payment_method' => $data['payment_method'] ?? 'unknown',
                    'order_id' => $order !== null ? $order->id : null,
                ]
            ));

            throw $e;
        } catch (Exception $e) {
            // Generic error handling for other exceptions
            Log::error('Generic error during voucher purchase', [
                'error' => $e->getMessage(),
                'order_id' => $order !== null ? $order->id : null,
                'user_id' => $user->id,
            ]);

            // Restore user balance if payment was processed
            if ($paymentResult !== null && $amountDebited > 0) {
                $this->restoreUserBalance($user, $amountDebited, $order);
            }

            if ($order) {
                $order->update([
                    'status' => OrderStatus::ERROR,
                    'last_error' => $e->getMessage(),
                    'balance_amount_used' => 0, // Reset balance used since we restored it
                ]);
            }

            // Broadcast error notification
            broadcast(new VoucherPurchaseError(
                userId: (string) $user->id,
                errorCode: 'PAYMENT_FAILED',
                errorMessage: $e->getMessage(),
                context: [
                    'product_id' => $product->id,
                    'payment_method' => $data['payment_method'] ?? 'unknown',
                    'order_id' => $order !== null ? $order->id : null,
                ]
            ));

            throw $e;
        }
    }

    /**
     * Process payment using only balance
     *
     * @return array<string, mixed>
     */
    private function processBalancePayment(User $user, Order $order, int $amount): array
    {

        if ($amount > 0) {
            Log::debug('Processing balance payment', [
                'amount' => $amount,
            ]);
            $result = $this->balanceService->processPayment($user, $amount, $order);

            // Update order with balance amount used
            $order->update([
                'balance_amount_used' => $amount,
            ]);
        } else {
            // No balance payment needed
            $result = null;
        }

        broadcast(new VoucherPurchaseNotification(
            userId: (string) $user->id,
            orderId: $order->id,
            status: 'completed',
            orderData: [
                'product_name' => $order->product->name ?? '',
                'amount_paid' => $result instanceof PaymentResult ? $result->amountDebited : 0,
                'remaining_balance' => $result instanceof PaymentResult ? $result->remainingBalance : 0,
                'payment_method' => $order->payment_method,
                'stripe_payment_id' => $order->stripe_payment_id,
            ],
            message: 'Your voucher purchase has been completed successfully'
        ));

        $response = [
            'order_id' => $order->id,
            'payment_method' => $order->payment_method,
            'amount_paid' => $result instanceof PaymentResult ? (int) ($result->amountDebited) : 0,
            'remaining_balance' => $result instanceof PaymentResult ? (int) ($result->remainingBalance) : 0,
        ];

        // Add payment-method specific fields
        if ($order->payment_method === 'mixed') {
            $response['balance_amount'] = (int) (($order->balance_amount_used ?? 0));
            $response['stripe_amount'] = (int) (($order->total_amount - ($order->balance_amount_used ?? 0)));
        }

        return $response;
    }

    /**
     * Restore user balance in case of error
     *
     * @param  int  $amount  Amount in cents to restore
     */
    private function restoreUserBalance(User $user, int $amount, ?Order $order): void
    {
        try {
            // Add credit back to user's balance
            CreditAccountService::addCredit(
                User::class,
                (string) $user->id,
                CreditTypes::CASH,
                $amount,
                (string) $user->id
            );

            Log::info('User balance restored after voucher purchase failure', [
                'user_id' => $user->id,
                'amount_cents' => $amount,
                'order_id' => $order instanceof Order ? $order->id : null,
            ]);

            // Broadcast balance restoration notification
            broadcast(new VoucherPurchaseNotification(
                userId: (string) $user->id,
                orderId: $order instanceof Order ? (string) $order->id : '',
                status: 'balance_restored',
                orderData: [
                    'amount_restored' => $amount,
                    'reason' => 'Voucher purchase failed - balance restored',
                ],
                message: 'Your balance has been restored due to purchase failure'
            ));
        } catch (Exception $e) {
            Log::error('Failed to restore user balance', [
                'user_id' => $user->id,
                'amount_cents' => $amount,
                'order_id' => $order instanceof Order ? $order->id : null,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - we want the original error to be returned
        }
    }
}
