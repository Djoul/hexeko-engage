<?php

declare(strict_types=1);

namespace App\Integrations\Vouchers\Amilon\Services;

use App\Integrations\Payments\Stripe\Exceptions\StripePaymentException;
use App\Integrations\Payments\Stripe\Models\StripePayment;
use App\Integrations\Vouchers\Amilon\Models\Product;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

/**
 * @deprecated
 * Use App\Integrations\Payments\Stripe\Services\StripeService instead
 * 2025-01-05
 */
class StripePaymentService
{
    protected StripeClient $stripeClient;

    /**
     * Allowed predefined amounts for vouchers (in euros)
     */
    public const ALLOWED_AMOUNTS = [20, 50, 100, 200, 500];

    /**
     * Minimum and maximum amounts for custom vouchers
     */
    public const MIN_AMOUNT = 10;

    public const MAX_AMOUNT = 1000;

    public function __construct(?StripeClient $stripeClient = null)
    {
        $secretKey = config('services.stripe.secret_key');
        if (! is_string($secretKey)) {
            throw new StripePaymentException('Stripe secret key not configured');
        }

        $this->stripeClient = $stripeClient ?? new StripeClient($secretKey);
    }

    /**
     * Create a payment intent for voucher purchase
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     *
     * @throws StripePaymentException
     */
    public function createPaymentIntent(
        Product $product,
        float $amount,
        string $userId,
        array $metadata = []
    ): array {
        try {
            // Validate amount
            $this->validateAmount($amount);

            // Calculate discounted price (example: 20% discount)
            $discountedAmount = $this->calculateDiscountedAmount($amount);

            // Ensure all metadata values are strings
            $stringMetadata = array_map(function ($value): string {
                if (is_string($value)) {
                    return $value;
                }
                if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                    return (string) $value;
                }

                return '';
            }, $metadata);

            // Create payment intent
            $paymentIntent = $this->stripeClient->paymentIntents->create([
                'amount' => $this->convertToSmallestUnit($discountedAmount, 'eur'),
                'currency' => 'eur',
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'metadata' => array_merge([
                    'user_id' => $userId,
                    'product_id' => $product->id,
                    'merchant_id' => $product->merchant_id,
                    'voucher_amount' => (string) $amount,
                    'type' => 'voucher_purchase',
                ], $stringMetadata),
            ]);

            // Store payment record
            $payment = $this->createPaymentRecord($paymentIntent, $userId, $product, $amount, $discountedAmount);

            return [
                'payment_intent_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
                'amount' => $discountedAmount,
                'original_amount' => $amount,
                'discount_percentage' => $this->getDiscountPercentage(),
                'payment_id' => $payment->id,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe API error creating payment intent', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'amount' => $amount,
            ]);

            throw new StripePaymentException(
                'Failed to create payment intent: '.$e->getMessage(),
                previous: $e
            );
        } catch (Exception $e) {
            Log::error('Error creating payment intent', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'amount' => $amount,
            ]);

            throw new StripePaymentException(
                'Failed to create payment intent: '.$e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Confirm a payment intent
     *
     * @throws StripePaymentException
     */
    public function confirmPaymentIntent(string $paymentIntentId): PaymentIntent
    {
        try {
            $paymentIntent = $this->stripeClient->paymentIntents->retrieve($paymentIntentId);

            if ($paymentIntent->status === 'requires_confirmation') {
                return $this->stripeClient->paymentIntents->confirm($paymentIntentId);
            }

            return $paymentIntent;
        } catch (ApiErrorException $e) {
            Log::error('Stripe API error confirming payment intent', [
                'error' => $e->getMessage(),
                'payment_intent_id' => $paymentIntentId,
            ]);

            throw new StripePaymentException(
                'Failed to confirm payment intent: '.$e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Retrieve a payment intent
     *
     * @throws StripePaymentException
     */
    public function retrievePaymentIntent(string $paymentIntentId): PaymentIntent
    {
        try {
            return $this->stripeClient->paymentIntents->retrieve($paymentIntentId);
        } catch (ApiErrorException $e) {
            Log::error('Stripe API error retrieving payment intent', [
                'error' => $e->getMessage(),
                'payment_intent_id' => $paymentIntentId,
            ]);

            throw new StripePaymentException(
                'Failed to retrieve payment intent: '.$e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Cancel a payment intent
     *
     * @throws StripePaymentException
     */
    public function cancelPaymentIntent(string $paymentIntentId): PaymentIntent
    {
        try {
            return $this->stripeClient->paymentIntents->cancel($paymentIntentId);
        } catch (ApiErrorException $e) {
            Log::error('Stripe API error cancelling payment intent', [
                'error' => $e->getMessage(),
                'payment_intent_id' => $paymentIntentId,
            ]);

            throw new StripePaymentException(
                'Failed to cancel payment intent: '.$e->getMessage(),
                previous: $e
            );
        } catch (Exception $e) {
            Log::error('Error cancelling payment intent', [
                'error' => $e->getMessage(),
                'payment_intent_id' => $paymentIntentId,
            ]);

            throw new StripePaymentException(
                'Failed to cancel payment intent: '.$e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Validate voucher amount
     *
     * @throws StripePaymentException
     */
    protected function validateAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new StripePaymentException('Amount must be positive');
        }

        if ($amount < self::MIN_AMOUNT) {
            throw new StripePaymentException(
                sprintf('Amount must be at least %d EUR', self::MIN_AMOUNT)
            );
        }

        if ($amount > self::MAX_AMOUNT) {
            throw new StripePaymentException(
                sprintf('Amount cannot exceed %d EUR', self::MAX_AMOUNT)
            );
        }
    }

    /**
     * Calculate discounted amount based on business rules
     */
    protected function calculateDiscountedAmount(float $amount): float
    {
        $discountPercentage = $this->getDiscountPercentage();

        return round($amount * (1 - $discountPercentage / 100), 2);
    }

    /**
     * Get discount percentage based on amount or other business rules
     */
    protected function getDiscountPercentage(): int
    {
        // Example: 20% discount for all vouchers
        // This can be made more complex based on amount tiers, user type, etc.
        return 20;
    }

    /**
     * Convert amount to smallest currency unit (cents for EUR)
     */
    protected function convertToSmallestUnit(float $amount, string $currency): int
    {
        // For EUR, USD, etc., multiply by 100 to get cents
        return (int) round($amount * 100);
    }

    /**
     * Create a payment record in database
     */
    protected function createPaymentRecord(
        PaymentIntent $paymentIntent,
        string $userId,
        Product $product,
        float $originalAmount,
        float $discountedAmount
    ): StripePayment {
        return DB::transaction(function () use ($paymentIntent, $userId, $product, $originalAmount, $discountedAmount) {
            return StripePayment::create([
                'user_id' => $userId,
                'stripe_payment_id' => $paymentIntent->id,
                'stripe_checkout_id' => null, // Not using checkout for payment intents
                'status' => 'pending',
                'amount' => $discountedAmount,
                'currency' => $paymentIntent->currency,
                'credit_amount' => (int) $originalAmount, // Store original voucher amount
                'credit_type' => 'voucher_credit',
                'metadata' => [
                    'product_id' => $product->id,
                    'merchant_id' => $product->merchant_id,
                    'merchant_name' => $product->merchant?->name,
                    'original_amount' => $originalAmount,
                    'discount_percentage' => $this->getDiscountPercentage(),
                ],
            ]);
        });
    }
}
