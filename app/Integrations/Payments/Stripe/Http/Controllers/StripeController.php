<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Integrations\Payments\Stripe\Actions\CreateCheckoutSessionAction;
use App\Integrations\Payments\Stripe\Http\Requests\CreateCheckoutSessionRequest;
use App\Integrations\Payments\Stripe\Http\Resources\StripePaymentResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Integrations/Stripe')]
class StripeController extends Controller
{
    public function __construct(
        protected CreateCheckoutSessionAction $createCheckoutSessionAction
    ) {}

    public function createCheckoutSession(CreateCheckoutSessionRequest $request): JsonResponse
    {
        $dto = $request->toDto();
        $result = $this->createCheckoutSessionAction->execute($dto);

        return response()->json([
            'checkout_url' => $result['checkout_url'],
            'session_id' => $result['session_id'],
            'payment' => new StripePaymentResource($result['payment']),
        ]);
    }
}
