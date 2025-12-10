<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Services;

use App\Integrations\Payments\Stripe\Contracts\StripeClientInterface;
use App\Integrations\Payments\Stripe\Exceptions\StripePaymentException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeClientAdapter implements StripeClientInterface
{
    private StripeClient $stripeClient;

    public function __construct(?StripeClient $stripeClient = null)
    {
        /** @var string $apiKey */
        $apiKey = config('services.stripe.secret_key');
        $this->stripeClient = $stripeClient ?? new StripeClient($apiKey);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function createCheckoutSession(array $params): array
    {
        try {
            /** @phpstan-ignore-next-line */
            $session = $this->stripeClient->checkout->sessions->create($params);

            return [
                'id' => $session->id,
                'url' => $session->url,
                'amount_total' => $session->amount_total,
                'currency' => $session->currency,
                'status' => $session->status,
                'metadata' => ($session->metadata !== null ? $session->metadata->toArray() : null) ?? [],
            ];
        } catch (ApiErrorException $e) {
            throw new StripePaymentException(
                'Stripe API error: '.$e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function createPaymentIntent(array $params): object
    {
        try {
            /** @phpstan-ignore-next-line */
            $paymentIntent = $this->stripeClient->paymentIntents->create($params);

            return (object) [
                'id' => $paymentIntent->id,
                'amount' => $paymentIntent->amount,
                'currency' => $paymentIntent->currency,
                'status' => $paymentIntent->status,
                'client_secret' => $paymentIntent->client_secret,
                'metadata' => ($paymentIntent->metadata !== null ? $paymentIntent->metadata->toArray() : null) ?? [],
            ];
        } catch (ApiErrorException $e) {
            throw new StripePaymentException(
                'Stripe API error: '.$e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Cancel a payment intent
     */
    public function cancelPaymentIntent(string $paymentIntentId): object
    {
        try {
            $paymentIntent = $this->stripeClient->paymentIntents->cancel($paymentIntentId);

            return (object) [
                'id' => $paymentIntent->id,
                'amount' => $paymentIntent->amount,
                'currency' => $paymentIntent->currency,
                'status' => $paymentIntent->status,
                'cancelled_at' => $paymentIntent->canceled_at,
                'metadata' => ($paymentIntent->metadata !== null ? $paymentIntent->metadata->toArray() : null) ?? [],
            ];
        } catch (ApiErrorException $e) {
            throw new StripePaymentException(
                'Stripe API error: '.$e->getMessage(),
                previous: $e
            );
        }
    }
}
