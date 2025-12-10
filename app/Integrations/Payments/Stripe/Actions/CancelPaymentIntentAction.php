<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Actions;

use App\Integrations\Payments\Stripe\Exceptions\StripePaymentException;
use App\Integrations\Payments\Stripe\Models\StripePayment;
use App\Integrations\Payments\Stripe\Services\StripeService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CancelPaymentIntentAction
{
    public function __construct(
        private readonly StripeService $stripeService
    ) {}

    /**
     * Cancel a payment intent
     *
     * @param  array{payment_intent_id: string, user_id: int|string}  $data
     * @return array{payment: StripePayment, cancelled: bool}
     */
    public function execute(array $data): array
    {
        // Validate required fields
        if (empty($data['payment_intent_id'])) {
            throw new InvalidArgumentException('Payment Intent ID is required');
        }

        if (empty($data['user_id'])) {
            throw new InvalidArgumentException('User ID is required');
        }

        $paymentIntentId = $data['payment_intent_id'];
        $userId = $data['user_id'];

        return DB::transaction(function () use ($paymentIntentId, $userId): array {
            // Find the payment in our database
            $payment = StripePayment::where('stripe_payment_id', $paymentIntentId)
                ->where('user_id', $userId)
                ->first();

            if (! $payment) {
                throw new StripePaymentException("Payment intent {$paymentIntentId} not found or does not belong to user");
            }

            // Check if payment can be cancelled (only pending payments can be cancelled)
            if ($payment->status != 'pending') {
                throw new StripePaymentException("Payment intent {$paymentIntentId} cannot be cancelled in current status: {$payment->status}");
            }

            // Cancel the payment intent via Stripe
            $cancelledPaymentIntent = $this->stripeService->cancelPaymentIntent($paymentIntentId);

            // Update local payment record
            $payment->update([
                'status' => 'cancelled',
            ]);

            return [
                'payment' => $payment->refresh(),
                'cancelled' => true,
            ];
        });
    }
}
