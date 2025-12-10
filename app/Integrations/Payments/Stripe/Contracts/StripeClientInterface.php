<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Contracts;

interface StripeClientInterface
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function createCheckoutSession(array $params): array;

    /**
     * @param  array<string, mixed>  $params
     */
    public function createPaymentIntent(array $params): object;

    /**
     * Cancel a payment intent
     */
    public function cancelPaymentIntent(string $paymentIntentId): object;
}
