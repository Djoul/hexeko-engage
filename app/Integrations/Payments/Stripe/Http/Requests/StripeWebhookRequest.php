<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Http\Requests;

use App\Integrations\Payments\Stripe\DTO\WebhookEventDTO;
use Illuminate\Foundation\Http\FormRequest;
use Log;

class StripeWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function toDto(): WebhookEventDTO
    {
        /** @var string $webhookSecret */
        $webhookSecret = config('services.stripe.webhook_secret', '');

        // Get the raw request body
        $payload = (string) $this->getContent();

        // Get the Stripe signature header
        $signature = (string) $this->header('Stripe-Signature', '');

        // Log for debugging
        Log::debug('Creating WebhookEventDTO', [
            'has_payload' => ! empty($payload),
            'payload_length' => strlen($payload),
            'has_signature' => ! empty($signature),
            'signature_preview' => substr($signature, 0, 50).(strlen($signature) > 50 ? '...' : ''),
            'webhook_secret_configured' => ! empty($webhookSecret),
        ]);

        return new WebhookEventDTO(
            payload: $payload,
            signature: $signature,
            secret: $webhookSecret,
        );
    }
}
