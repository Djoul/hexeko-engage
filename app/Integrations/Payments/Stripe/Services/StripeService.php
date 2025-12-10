<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Services;

use App\Integrations\Payments\Stripe\Contracts\StripeClientInterface;
use App\Integrations\Payments\Stripe\DTO\CheckoutSessionDTO;

class StripeService
{
    protected StripeClientInterface $stripeClient;

    public function __construct(?StripeClientInterface $stripeClient = null)
    {
        $this->stripeClient = $stripeClient ?? new StripeClientAdapter;
    }

    /**
     * @return array<string, mixed>
     */
    public function createCheckoutSession(CheckoutSessionDTO $dto): array
    {
        return $this->stripeClient->createCheckoutSession([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $dto->currency,
                    'product_data' => ['name' => $dto->productName],
                    'unit_amount' => $dto->getAmountInCents(),
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $dto->successUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $dto->cancelUrl,
            'metadata' => [
                'user_id' => $dto->userId,
                'credit_type' => $dto->creditType,
                'credit_amount' => (string) $dto->creditAmount,
            ],
            'expires_at' => time() + (30 * 60), // 30 minutes
        ]);
    }

    /**
     * Create a Payment Intent
     *
     * @param  float  $amount  Amount in euros
     * @param  array<string, mixed>  $metadata
     * @return object Payment Intent object from Stripe
     */
    public function createPaymentIntent(float $amount, array $metadata = []): object
    {
        return $this->stripeClient->createPaymentIntent([
            'amount' => $this->convertToSmallestUnit($amount),
            'currency' => 'eur',
            'metadata' => $metadata,
        ]);
    }

    /**
     * Cancel a Payment Intent
     *
     * @param  string  $paymentIntentId  The Stripe payment intent ID
     * @return object Cancelled Payment Intent object from Stripe
     */
    public function cancelPaymentIntent(string $paymentIntentId): object
    {
        return $this->stripeClient->cancelPaymentIntent($paymentIntentId);
    }

    /**
     * Convert amount to smallest currency unit (cents for EUR)
     */
    private function convertToSmallestUnit(float $amount): int
    {
        // For most currencies, multiply by 100 to get cents
        // This could be extended to handle zero-decimal currencies
        return (int) round($amount * 100);
    }
}
