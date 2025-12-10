# Deprecated Amilon Stripe Routes

## Routes to be marked as deprecated

The following routes were used by Amilon's Stripe integration and should be marked as deprecated:

### Payment Routes
```php
// @deprecated Use /api/v1/payments/stripe/payment-intent instead
Route::post('payments/create-intent', [PaymentController::class, 'createPaymentIntent']);

// @deprecated Payment confirmation should be done via Stripe.js on frontend
Route::post('payments/{paymentIntentId}/confirm', [PaymentController::class, 'confirmPaymentIntent']);

// @deprecated Use GET /api/v1/payments/stripe/payments/{paymentId} instead
Route::get('payments/{paymentIntentId}', [PaymentController::class, 'getPaymentIntent']);

// @deprecated Use POST /api/v1/payments/stripe/payment-intent/{paymentIntentId}/cancel instead
Route::post('payments/{paymentIntentId}/cancel', [PaymentController::class, 'cancelPaymentIntent']);
```

### Webhook Route
```php
// @deprecated Use POST /api/v1/payments/stripe/webhook instead
Route::post('stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);
```

## Notes

These routes were previously defined in the Amilon integration but have been deprecated in favor of the generic Stripe integration routes. The controllers now throw `DeprecatedFeatureException` when accessed.

### Full deprecated routes with prefixes:
- POST `/api/v1/vouchers/amilon/payments/create-intent`
- POST `/api/v1/vouchers/amilon/payments/{paymentIntentId}/confirm`
- GET `/api/v1/vouchers/amilon/payments/{paymentIntentId}`
- POST `/api/v1/vouchers/amilon/payments/{paymentIntentId}/cancel`
- POST `/api/v1/vouchers/amilon/stripe/webhook`
