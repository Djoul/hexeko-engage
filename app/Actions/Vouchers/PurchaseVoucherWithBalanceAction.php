<?php

declare(strict_types=1);

namespace App\Actions\Vouchers;

use App\Events\Vouchers\VoucherPurchaseError;
use App\Events\Vouchers\VoucherPurchaseNotification;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidPaymentMethodException;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Integrations\Vouchers\Amilon\Models\Product;
use App\Integrations\Vouchers\Amilon\Services\StripePaymentService;
use App\Models\CreditBalance;
use App\Models\User;
use App\Services\Payments\BalancePaymentService;
use App\Services\Payments\MixedPaymentService;
use App\Services\Payments\PaymentMethodResult;
use App\Services\Payments\PaymentMethodSelector;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @deprecated
 * Use App\Actions\Vouchers\PurchaseVoucherAction instead
 * 2025-01-05
 */
class PurchaseVoucherWithBalanceAction
{
    public function __construct(
        private readonly PaymentMethodSelector $selector,
        private readonly BalancePaymentService $balanceService,
        private readonly MixedPaymentService $mixedService,
        private readonly StripePaymentService $stripeService
    ) {}

    /**
     * @param  array{product_id: string, payment_method?: string}  $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            throw new Exception('User not authenticated');
        }

        $product = Product::findOrFail($data['product_id']);
        if (! $product instanceof Product) {
            throw new Exception('Product not found');
        }

        // Debug logging
        Log::info('Product details', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'product_price_type' => gettype($product->price),
            'merchant_id' => $product->merchant_id,
        ]);

        return DB::transaction(function () use ($user, $product, $data): array {
            // Create the order
            Log::info('Creating order', [
                'product_price' => $product->price,
                'amount' => $product->price,
                'total_amount' => $product->price,
                'payment_method' => $data['payment_method'] ?? 'auto',
            ]);

            $order = Order::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'merchant_id' => $product->merchant_id,
                'external_order_id' => 'ORD-'.now()->format('YmdHis').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                'amount' => $product->price,
                'total_amount' => $product->price,
                // 'payment_status' => 'pending', // Column removed
                'payment_method' => $data['payment_method'] ?? 'auto',
            ]);

            // Load the product relationship
            $order->load('product');

            // Broadcast order creation notification
            broadcast(new VoucherPurchaseNotification(
                userId: (string) $user->id,
                orderId: (string) $order->id,
                status: 'created',
                orderData: [
                    'product_name' => $product->name ?? '',
                    'amount' => $product->price,
                    'merchant_id' => $product->merchant_id,
                ],
                message: 'Your voucher order has been created'
            ));

            // Determine the payment method if auto
            if ($order->payment_method === 'auto' || $order->payment_method === 'mixed') {
                /** @var User $user */
                $userBalance = $this->getUserBalance($user);
                // Product price is already in euros (not cents)
                $productPriceInEuros = $product->price;
                if ($productPriceInEuros === null) {
                    throw new Exception('Product price not set');
                }
                $paymentMethod = $this->selector->determinePaymentMethod(
                    $productPriceInEuros,
                    $userBalance
                );
            } else {
                // Manual selection
                if (! array_key_exists('payment_method', $data)) {
                    throw new Exception('Payment method not specified');
                }
                $productPrice = $product->net_price;
                if ($productPrice === null) {
                    throw new Exception('Product price not set');
                }
                $paymentMethod = $this->validateManualPaymentMethod(
                    $data['payment_method'],
                    $productPrice, // Price is already in euros
                    $this->getUserBalance($user)
                );
            }

            // Process payment based on method
            try {
                $result = match ($paymentMethod->method) {
                    'balance' => $this->processBalancePayment($user, $paymentMethod->balanceAmount, $order),
                    'mixed' => $this->processMixedPayment($user, $order, $paymentMethod),
                    'stripe' => $this->processStripePayment($user, $order, $product),
                    default => throw new InvalidPaymentMethodException('Invalid payment method: '.$paymentMethod->method)
                };

                // Update order with final payment method
                if ($order->payment_method === 'auto') {
                    $order->update(['payment_method' => $paymentMethod->method]);
                }

                return $result;
            } catch (Exception $e) {
                // $order->update(['payment_status' => 'failed']); // Column removed

                // Broadcast error notification
                broadcast(new VoucherPurchaseError(
                    userId: (string) $user->id,
                    errorCode: 'PAYMENT_FAILED',
                    errorMessage: $e->getMessage(),
                    context: [
                        'order_id' => $order->id,
                        'payment_method' => $paymentMethod->method,
                    ]
                ));

                throw $e;
            }
        });
    }

    private function getUserBalance(User $user): float
    {
        $balanceValue = CreditBalance::where('owner_id', (string) $user->id)
            ->where('owner_type', User::class)
            ->where('type', 'cash')
            ->value('balance');

        /** @var int $balanceInCents */
        $balanceInCents = is_numeric($balanceValue) ? (int) $balanceValue : 0;

        // Debug logging
        Log::info('getUserBalance called', [
            'user_id' => $user->id,
            'owner_id_query' => (string) $user->id,
            'balance_in_cents' => $balanceInCents,
            'balance_in_euros' => (float) ($balanceInCents / 100),
        ]);

        // Convert cents to euros
        return (float) ($balanceInCents / 100);
    }

    private function validateManualPaymentMethod(string $method, float $orderAmount, float $userBalance): PaymentMethodResult
    {
        // Debug logging
        Log::info('validateManualPaymentMethod called', [
            'method' => $method,
            'orderAmount' => $orderAmount,
            'userBalance' => $userBalance,
            'comparison' => $userBalance < $orderAmount ? 'insufficient' : 'sufficient',
        ]);

        if ($method === 'balance' && $userBalance < $orderAmount) {
            throw new InsufficientBalanceException('Insufficient balance for this purchase');
        }

        return new PaymentMethodResult(
            method: $method,
            balanceAmount: $method === 'balance' ? $orderAmount : 0.00,
            stripeAmount: $method === 'stripe' ? $orderAmount : 0.00
        );
    }

    /**
     * @return array{order_id: int, payment_method: string, payment_status: string, amount_paid: int, remaining_balance: int}
     */
    private function processBalancePayment(User $user, float $amount, Order $order): array
    {
        $result = $this->balanceService->processPayment($user, $amount, $order);

        // Columns removed
        // $order->update([
        //     'payment_status' => 'completed',
        //     'payment_completed_at' => now(),
        // ]);

        // Broadcast successful payment notification
        broadcast(new VoucherPurchaseNotification(
            userId: (string) $user->id,
            orderId: (string) $order->id,
            status: 'completed',
            orderData: [
                'product_name' => $order->product->name ?? '',
                'amount_paid' => $result->amountDebited,
                'remaining_balance' => $result->remainingBalance,
                'payment_method' => 'balance',
            ],
            message: 'Your voucher purchase has been completed successfully'
        ));

        return [
            'order_id' => (int) $order->id,
            'payment_method' => 'balance',
            'payment_status' => 'completed',
            'amount_paid' => (int) ($result->amountDebited * 100), // Convert to cents
            'remaining_balance' => (int) ($result->remainingBalance * 100), // Convert to cents
        ];
    }

    /**
     * @return array{order_id: int, payment_method: string, payment_status: string, balance_amount: int, stripe_amount: int, total_amount: int, payment_intent_id: string, client_secret: string, requires_stripe_payment: true}
     */
    private function processMixedPayment(User $user, Order $order, PaymentMethodResult $paymentMethod): array
    {
        $result = $this->mixedService->processPayment(
            $user,
            $order,
            $paymentMethod->balanceAmount,
            $paymentMethod->stripeAmount
        );

        // Broadcast notification for mixed payment requiring Stripe confirmation
        broadcast(new VoucherPurchaseNotification(
            userId: (string) $user->id,
            orderId: (string) $order->id,
            status: 'pending_stripe_payment',
            orderData: [
                'product_name' => $order->product->name ?? '',
                'balance_amount' => $paymentMethod->balanceAmount,
                'stripe_amount' => $paymentMethod->stripeAmount,
                'payment_intent_id' => $result->stripePaymentIntentId,
                'payment_method' => 'mixed',
            ],
            message: 'Balance payment completed. Please complete the Stripe payment to finalize your order.'
        ));

        return [
            'order_id' => (int) $order->id,
            'payment_method' => 'mixed',
            'payment_status' => 'pending', // Will be completed after Stripe payment
            'balance_amount' => (int) ($paymentMethod->balanceAmount * 100), // Convert to cents
            'stripe_amount' => (int) ($paymentMethod->stripeAmount * 100), // Convert to cents
            'total_amount' => (int) $order->total_amount,
            'payment_intent_id' => $result->stripePaymentIntentId,
            'client_secret' => $result->stripeClientSecret,
            'requires_stripe_payment' => true,
        ];
    }

    /**
     * @return array{order_id: int, payment_method: string, payment_status: string, payment_intent_id: string, client_secret: string, amount: int, requires_stripe_payment: true}
     */
    private function processStripePayment(User $user, Order $order, Product $product): array
    {
        /** @var array{payment_intent_id: string, client_secret: string, amount: float, original_amount: float, discount_percentage: float, payment_id: int} $stripeData */
        $stripeData = $this->stripeService->createPaymentIntent(
            $product,
            $order->total_amount ?? 0.0, // Convert cents to euros
            (string) $user->id,
            [
                'order_id' => (string) $order->id,
                'payment_type' => 'stripe_only',
            ]
        );

        // Broadcast notification for Stripe payment
        broadcast(new VoucherPurchaseNotification(
            userId: (string) $user->id,
            orderId: (string) $order->id,
            status: 'pending_stripe_payment',
            orderData: [
                'product_name' => $product->name ?? '',
                'amount' => $stripeData['amount'],
                'payment_intent_id' => $stripeData['payment_intent_id'],
                'payment_method' => 'stripe',
            ],
            message: 'Please complete the payment to finalize your voucher order.'
        ));

        return [
            'order_id' => (int) $order->id,
            'payment_method' => 'stripe',
            'payment_status' => 'pending',
            'payment_intent_id' => (string) $stripeData['payment_intent_id'],
            'client_secret' => (string) $stripeData['client_secret'],
            'amount' => (int) $stripeData['amount'],
            'requires_stripe_payment' => true,
        ];
    }
}
