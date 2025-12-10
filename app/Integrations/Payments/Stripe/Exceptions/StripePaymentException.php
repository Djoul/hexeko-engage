<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class StripePaymentException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'error' => 'Payment processing failed',
            'message' => 'Unable to process payment. Please try again.',
        ], 422);
    }
}
