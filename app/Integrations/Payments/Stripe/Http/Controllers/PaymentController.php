<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Integrations\Payments\Stripe\Actions\CancelPaymentIntentAction;
use App\Integrations\Payments\Stripe\Actions\CreatePaymentIntentAction;
use App\Integrations\Payments\Stripe\Http\Requests\CreatePaymentIntentRequest;
use App\Integrations\Payments\Stripe\Http\Resources\StripePaymentResource;
use App\Integrations\Payments\Stripe\Models\StripePayment;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly CreatePaymentIntentAction $createPaymentIntentAction,
        private readonly CancelPaymentIntentAction $cancelPaymentIntentAction
    ) {}

    /**
     * List user's payments
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401, 'Unauthorized');
        }

        $payments = StripePayment::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return StripePaymentResource::collection($payments);
    }

    /**
     * Show a specific payment
     */
    public function show(Request $request, string $paymentId): StripePaymentResource
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401, 'Unauthorized');
        }

        $payment = StripePayment::where('user_id', $user->id)
            ->where('id', $paymentId)
            ->firstOrFail();

        return new StripePaymentResource($payment);
    }

    /**
     * Create a new payment intent
     */
    public function createPaymentIntent(CreatePaymentIntentRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401, 'Unauthorized');
        }

        $data = array_merge(
            $request->validated(),
            ['user_id' => $user->id]
        );

        $result = $this->createPaymentIntentAction->execute($data);

        $payment = $result['payment'];
        if (! $payment instanceof StripePayment) {
            throw new RuntimeException('Invalid payment object returned');
        }

        return response()->json([
            'data' => [
                'payment_id' => $payment->id,
                'client_secret' => $result['client_secret'],
                'payment_intent_id' => $result['payment_intent_id'],
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'credit_amount' => $payment->credit_amount,
                'credit_type' => $payment->credit_type,
            ],
        ], 201);
    }

    /**
     * Cancel a payment intent
     */
    public function cancelPaymentIntent(Request $request, string $paymentIntentId): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user instanceof User) {
                abort(401, 'Unauthorized');
            }

            $result = $this->cancelPaymentIntentAction->execute([
                'payment_intent_id' => $paymentIntentId,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'data' => [
                    'payment_id' => $result['payment']->id,
                    'payment_intent_id' => $result['payment']->stripe_payment_id,
                    'status' => $result['payment']->status,
                    'cancelled' => $result['cancelled'],
                    'cancelled_at' => null,
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Invalid request',
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Payment cancellation failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
