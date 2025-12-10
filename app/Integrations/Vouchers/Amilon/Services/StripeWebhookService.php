<?php

declare(strict_types=1);

namespace App\Integrations\Vouchers\Amilon\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\Vouchers\VoucherPaymentStatusUpdate;
use App\Integrations\Payments\Stripe\Events\StripePaymentFailed;
use App\Integrations\Payments\Stripe\Events\StripePaymentSucceeded;
use App\Integrations\Payments\Stripe\Exceptions\WebhookVerificationException;
use App\Integrations\Payments\Stripe\Models\StripePayment;
use App\Integrations\Vouchers\Amilon\Enums\VoucherNotificationStatus;
use App\Integrations\Vouchers\Amilon\Models\ProcessedWebhookEvent;
use App\Integrations\Vouchers\Amilon\Models\Product;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Webhook;

/**
 * @deprecated
 * Use App\Integrations\Payments\Stripe\Actions\HandleStripeWebhookAction instead
 * 2025-01-05
 */
class StripeWebhookService
{
    protected AmilonOrderService $amilonOrderService;

    public function __construct(AmilonOrderService $amilonOrderService)
    {
        $this->amilonOrderService = $amilonOrderService;
    }

    /**
     * Validate webhook signature
     *
     * @throws WebhookVerificationException
     */
    public function validateSignature(string $payload, string $signature, string $secret): Event
    {
        try {
            return Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Invalid webhook signature', [
                'error' => $e->getMessage(),
                'signature' => substr($signature, 0, 20).'...',
            ]);

            throw new WebhookVerificationException(
                'Invalid webhook signature',
                previous: $e
            );
        }
    }

    /**
     * Process webhook event
     */
    public function processEvent(Event $event): void
    {
        // Check for duplicate events
        if ($this->isDuplicateEvent((string) $event->id)) {
            Log::info('Skipping duplicate webhook event', ['event_id' => $event->id]);

            return;
        }

        // Mark event as processed
        ProcessedWebhookEvent::create([
            'event_id' => (string) $event->id,
            'event_type' => (string) $event->type,
            'processed_at' => now(),
        ]);

        // Process based on event type
        match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($event),
            default => Log::info('Unhandled webhook event type', ['type' => $event->type])
        };
    }

    /**
     * Handle successful payment intent
     */
    protected function handlePaymentIntentSucceeded(Event $event): void
    {
        /** @var PaymentIntent $paymentIntent */
        $paymentIntent = $event->data->object;

        DB::transaction(function () use ($paymentIntent): void {
            // Find the payment record
            $payment = StripePayment::where('stripe_payment_id', $paymentIntent->id)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                Log::error('Payment record not found for successful payment intent', [
                    'payment_intent_id' => $paymentIntent->id,
                ]);

                return;
            }

            // Skip if already processed
            if ($payment->status === PaymentStatus::COMPLETED->value) {
                Log::info('Payment already completed', ['payment_id' => $payment->id]);

                return;
            }

            // Update payment status
            $payment->status = PaymentStatus::COMPLETED->value;
            $payment->processed_at = now();
            $payment->save();

            // Dispatch success event
            event(new StripePaymentSucceeded($payment));

            // Broadcast WebSocket notification
            broadcast(new VoucherPaymentStatusUpdate(
                userId: $payment->user_id,
                paymentId: (string) $payment->id,
                status: PaymentStatus::COMPLETED->value,
                paymentMethod: PaymentMethod::STRIPE->value,
                metadata: [
                    'stripe_payment_id' => $payment->stripe_payment_id,
                    'amount' => $payment->amount,
                    'credit_amount' => $payment->credit_amount,
                    'credit_type' => $payment->credit_type,
                ]
            ));
        });
    }

    /**
     * Handle failed payment intent
     */
    protected function handlePaymentIntentFailed(Event $event): void
    {
        /** @var PaymentIntent $paymentIntent */
        $paymentIntent = $event->data->object;

        DB::transaction(function () use ($paymentIntent): void {
            // Find the payment record
            $payment = StripePayment::where('stripe_payment_id', $paymentIntent->id)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                Log::error('Payment record not found for failed payment intent', [
                    'payment_intent_id' => $paymentIntent->id,
                ]);

                return;
            }

            // Update payment status
            $payment->status = PaymentStatus::FAILED->value;
            $payment->processed_at = now();

            // Handle last_payment_error which can be either an object or array
            $lastPaymentError = $paymentIntent->last_payment_error;
            $errorMessage = null;
            $errorCode = null;

            if ($lastPaymentError) {
                if (is_object($lastPaymentError)) {
                    // Stripe objects use magic __get method, so we need to access properties directly
                    $errorMessage = $lastPaymentError->message ?? null;
                    $errorCode = $lastPaymentError->code ?? null;
                } elseif (is_array($lastPaymentError)) {
                    $errorMessage = $lastPaymentError['message'] ?? null;
                    $errorCode = $lastPaymentError['code'] ?? null;
                }
            }

            $payment->error_message = $errorMessage;
            $payment->metadata = array_merge($payment->metadata ?? [], [
                'failure_code' => $errorCode,
                'failure_message' => $errorMessage,
            ]);
            $payment->save();

            // Update associated order if exists
            /** @var array<string, mixed>|null $paymentMetadata */
            //            $paymentMetadata = $payment->metadata;
            //            if (is_array($paymentMetadata) && ($paymentMetadata['order_id'] ?? null)) {
            //                Order::where('id', $paymentMetadata['order_id'])
            //                    ->update(['status' => OrderStatus::ERROR->value]);
            //            }

            // Dispatch failure event
            event(new StripePaymentFailed($payment));

            // Broadcast WebSocket notification
            broadcast(new VoucherPaymentStatusUpdate(
                userId: $payment->user_id,
                paymentId: (string) $payment->id,
                status: PaymentStatus::FAILED->value,
                paymentMethod: PaymentMethod::STRIPE->value,
                metadata: [
                    'stripe_payment_id' => $payment->stripe_payment_id,
                    'amount' => $payment->amount,
                    'credit_amount' => $payment->credit_amount,
                    'credit_type' => $payment->credit_type,
                    'error_message' => $payment->error_message,
                    'failure_code' => $errorCode,
                ]
            ));
        });
    }

    /**
     * Create Amilon order after successful payment
     */
    protected function createAmilonOrder(StripePayment $payment): void
    {
        try {
            // Get product
            /** @var array<string, mixed>|null $metadata */
            $metadata = $payment->metadata;
            $productId = is_array($metadata) && array_key_exists('product_id', $metadata) ? $metadata['product_id'] : null;
            if (! is_string($productId) && ! is_int($productId)) {
                throw new Exception('Product ID not found in payment metadata');
            }

            /** @var Product|null $product */
            $product = Product::find($productId);
            if (! $product) {
                throw new Exception('Product not found: '.$productId);
            }

            // Calculate quantity (voucher amount)
            $quantity = (int) $payment->credit_amount;

            // Generate external order ID
            $externalOrderId = $this->amilonOrderService->generateExternalOrderId();

            // Create order in Amilon
            $orderData = $this->amilonOrderService->createOrder(
                $product,
                $quantity,
                $externalOrderId,
                $payment->user_id,
                $payment->stripe_payment_id
            );

            // Update payment metadata with order info
            $payment->metadata = array_merge($payment->metadata ?? [], [
                'order_id' => $orderData['order_id'] ?? null,
                'external_order_id' => $externalOrderId,
                'order_status' => $orderData['status'] ?? null,
            ]);
            $payment->save();

            Log::info('Amilon order created successfully', [
                'payment_id' => $payment->id,
                'order_id' => $orderData['order_id'] ?? null,
                'external_order_id' => $externalOrderId,
            ]);

            // Broadcast WebSocket notification about successful order creation
            broadcast(new VoucherPaymentStatusUpdate(
                userId: $payment->user_id,
                paymentId: (string) $payment->id,
                status: VoucherNotificationStatus::ORDER_CREATED->value,
                paymentMethod: PaymentMethod::STRIPE->value,
                metadata: [
                    'stripe_payment_id' => $payment->stripe_payment_id,
                    'amount' => $payment->amount,
                    'credit_amount' => $payment->credit_amount,
                    'credit_type' => $payment->credit_type,
                    'order_id' => $orderData['order_id'] ?? null,
                    'external_order_id' => $externalOrderId,
                    'message' => 'Voucher order created successfully',
                ]
            ));
        } catch (Exception $e) {
            Log::error('Failed to create Amilon order', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update payment with error info
            $payment->metadata = array_merge($payment->metadata ?? [], [
                'amilon_error' => $e->getMessage(),
                'amilon_error_time' => now()->toIso8601String(),
            ]);
            $payment->save();

            // Broadcast WebSocket notification about order creation failure
            broadcast(new VoucherPaymentStatusUpdate(
                userId: $payment->user_id,
                paymentId: (string) $payment->id,
                status: VoucherNotificationStatus::ORDER_CREATION_FAILED->value,
                paymentMethod: PaymentMethod::STRIPE->value,
                metadata: [
                    'stripe_payment_id' => $payment->stripe_payment_id,
                    'amount' => $payment->amount,
                    'credit_amount' => $payment->credit_amount,
                    'credit_type' => $payment->credit_type,
                    'error_message' => 'Failed to create voucher order: '.$e->getMessage(),
                    'error_type' => 'amilon_order_creation',
                ]
            ));

            // Re-throw to trigger rollback
            throw $e;
        }
    }

    /**
     * Check if event has already been processed
     */
    protected function isDuplicateEvent(string $eventId): bool
    {
        return ProcessedWebhookEvent::where('event_id', $eventId)->exists();
    }
}
