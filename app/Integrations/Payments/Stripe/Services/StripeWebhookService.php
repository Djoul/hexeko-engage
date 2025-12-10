<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Services;

use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookService
{
    /**
     * Construct and verify a Stripe webhook event
     *
     * @throws SignatureVerificationException
     */
    public function constructEvent(string $payload, string $signature, string $secret): Event
    {
        return Webhook::constructEvent($payload, $signature, $secret);
    }
}
