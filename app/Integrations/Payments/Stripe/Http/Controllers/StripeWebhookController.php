<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Integrations\Payments\Stripe\Actions\HandleStripeWebhookAction;
use App\Integrations\Payments\Stripe\Exceptions\WebhookVerificationException;
use App\Integrations\Payments\Stripe\Http\Requests\StripeWebhookRequest;
use Context;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Log;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected HandleStripeWebhookAction $handleStripeWebhookAction
    ) {}

    /**
     * Handle Stripe webhook events.
     *
     * Processes incoming Stripe webhook events for payment status updates.
     *
     * @group Payments/Stripe
     *
     * @response 200 {
     *   "status": "success"
     * }
     * @response 400 {
     *   "error": "Invalid webhook signature"
     * }
     * @response 404 {
     *   "error": "Payment not found"
     * }
     */
    public function handleWebhook(StripeWebhookRequest $request): JsonResponse
    {
        Context::add('is_stripe_webhook', true);

        try {
            $dto = $request->toDto();

            Log::debug('Stripe webHook controller called', [
                'has_payload' => ! empty($dto->payload),
                'has_signature' => ! empty($dto->signature),
                'has_secret' => ! empty($dto->secret),
            ]);

            $this->handleStripeWebhookAction->execute($dto);

            return response()->json(['status' => 'success']);
        } catch (WebhookVerificationException $e) {
            Log::warning('Stripe webhook verification failed', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);

            return response()->json(['error' => 'Webhook verification failed'], 400);
        } catch (ModelNotFoundException $e) {
            Log::warning('Stripe webhook payment not found', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);

            return response()->json(['error' => 'Payment not found'], 404);
        } catch (Exception $e) {
            Log::error('Stripe webhook processing error', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            // During testing, return error details in response
            if (app()->environment('testing')) {
                return response()->json([
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                ], 500);
            }

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }
}
