<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class WebhookVerificationException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'error' => 'Webhook verification failed',
            'message' => 'Invalid webhook signature.',
        ], 400);
    }
}
