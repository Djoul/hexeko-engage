<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Actions;

use App\Integrations\Payments\Stripe\DTO\CheckoutSessionDTO;
use App\Integrations\Payments\Stripe\Exceptions\StripePaymentException;
use App\Integrations\Payments\Stripe\Models\StripePayment;
use App\Integrations\Payments\Stripe\Services\StripeService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateCheckoutSessionAction
{
    public function __construct(
        protected StripeService $stripeService
    ) {}

    /**
     * @return array{checkout_url: string, session_id: string, payment: StripePayment}
     */
    public function execute(CheckoutSessionDTO $dto): array
    {
        try {
            return DB::transaction(function () use ($dto): array {
                // Create Stripe session
                $session = $this->stripeService->createCheckoutSession($dto);

                assert(is_string($session['url']));
                assert(is_string($session['id']));

                // Store payment record
                $payment = StripePayment::create([
                    'user_id' => $dto->userId,
                    'stripe_checkout_id' => $session['id'],
                    'status' => 'pending',
                    'amount' => $dto->amount,
                    'currency' => $dto->currency,
                    'credit_amount' => $dto->creditAmount,
                    'credit_type' => $dto->creditType,
                    'metadata' => [
                        'product_name' => $dto->productName,
                    ],
                ]);

                return [
                    'checkout_url' => $session['url'],
                    'session_id' => $session['id'],
                    'payment' => $payment,
                ];
            });
        } catch (Exception $e) {
            Log::error('Stripe checkout creation failed', [
                'user_id' => $dto->userId,
                'error' => $e->getMessage(),
            ]);

            throw new StripePaymentException(
                'Unable to create payment session',
                previous: $e
            );
        }
    }
}
