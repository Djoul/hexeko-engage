<?php

declare(strict_types=1);

use App\Integrations\Payments\Stripe\Http\Controllers\PaymentController;
use App\Integrations\Payments\Stripe\Http\Controllers\StripeController;
use Illuminate\Support\Facades\Route;

// use App\Integrations\Payments\Stripe\Http\Controllers\StripeWebhookController;

/**
 * @group Integrations/Payments/Stripe
 *
 * @authenticated
 *
 * Routes de l'intégration Stripe pour les paiements
 *
 * Ce module permet de gérer les paiements via Stripe (Payment Intents et Checkout Sessions)
 */
Route::middleware(['auth.cognito', 'check.permission'])->group(function (): void {
    Route::group(['prefix' => 'api/v1/payments/stripe'], function (): void {
        // Payment Intent endpoints
        Route::post('/payment-intent', [PaymentController::class, 'createPaymentIntent'])
            ->name('stripe.payment-intent.create');

        Route::get('/payments', [PaymentController::class, 'index'])
            ->name('stripe.payments.index');

        Route::get('/payments/{payment}', [PaymentController::class, 'show'])
            ->name('stripe.payments.show');

        // Payment Intent cancellation
        Route::post('/payment-intent/{paymentIntentId}/cancel', [PaymentController::class, 'cancelPaymentIntent'])
            ->name('stripe.payment-intent.cancel');

        // Checkout Session endpoint (existing)
        Route::post('/checkout', [StripeController::class, 'createCheckoutSession'])
            ->name('stripe.checkout');
    });
});

// // Stripe webhook route (outside auth middleware)
// Route::post('api/v1/payments/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])
//    ->name('stripe.webhook');
