<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Actions;

use App\Enums\CreditTypes;
use App\Integrations\Payments\Stripe\Exceptions\StripePaymentException;
use App\Integrations\Payments\Stripe\Models\StripePayment;
use App\Integrations\Payments\Stripe\Services\StripeService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreatePaymentIntentAction
{
    public function __construct(
        private readonly StripeService $stripeService
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        // Validate required fields
        if (empty($data['user_id'])) {
            throw new InvalidArgumentException('User ID is required for payment processing');
        }

        // Validate amount
        if (($data['amount'] ?? 0) <= 0) {
            throw new StripePaymentException('Amount must be greater than 0');
        }

        $amountValue = $data['amount'] ?? 0;
        $amount = is_numeric($amountValue) ? (int) $amountValue : 0;

        $currencyValue = $data['currency'] ?? null;
        $currency = is_string($currencyValue) ? $currencyValue : 'eur';

        $userId = $data['user_id'];

        $metadataValue = $data['metadata'] ?? null;
        $metadata = is_array($metadataValue) ? $metadataValue : [];

        // Always include user_id in metadata
        $userIdString = is_scalar($userId) ? (string) $userId : '';
        $metadata['user_id'] = $userIdString;

        /** @return array<string, mixed> */
        return DB::transaction(function () use ($amount, $currency, $userId, $metadata, $data): array {
            // Create Payment Intent via Stripe
            $paymentIntent = $this->stripeService->createPaymentIntent($amount / 100, $metadata);

            // Extract properties safely from Stripe PaymentIntent object
            $paymentIntentId = $paymentIntent->id ?? '';
            $clientSecret = $paymentIntent->client_secret ?? '';

            // Create local payment record with required fields
            $payment = StripePayment::create([
                'user_id' => $userId,
                'stripe_payment_id' => $paymentIntentId,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'pending',
                'metadata' => $metadata,
                'credit_amount' => $data['credit_amount'] ?? 0,
                'credit_type' => CreditTypes::CASH,
            ]);

            return [
                'payment' => $payment,
                'client_secret' => $clientSecret,
                'payment_intent_id' => $paymentIntentId,
            ];
        });
    }
}
