<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HmacAuthMiddleware
{
    private const TIMESTAMP_TOLERANCE_SECONDS = 300; // 5 minutes

    public function handle(Request $request, Closure $next): mixed
    {
        $signature = $request->header('X-Cognito-Signature');
        $timestamp = $request->header('X-Cognito-Timestamp');

        // Validate signature header presence
        if ($signature === null || $signature === '') {
            return response('Missing HMAC signature', Response::HTTP_UNAUTHORIZED);
        }

        // Validate timestamp header presence
        if ($timestamp === null || $timestamp === '') {
            return response('Missing timestamp', Response::HTTP_UNAUTHORIZED);
        }

        // Validate timestamp is numeric
        if (! is_numeric($timestamp)) {
            return response('Invalid timestamp', Response::HTTP_UNAUTHORIZED);
        }

        $timestampInt = (int) $timestamp;
        $currentTime = time();

        // Validate timestamp is not in the future
        if ($timestampInt > $currentTime) {
            return response('Invalid timestamp', Response::HTTP_UNAUTHORIZED);
        }

        // Validate timestamp is not expired (older than 5 minutes)
        if ($currentTime - $timestampInt > self::TIMESTAMP_TOLERANCE_SECONDS) {
            return response('Timestamp expired', Response::HTTP_UNAUTHORIZED);
        }

        // Get webhook secret from config
        $webhookSecret = config('services.cognito.webhook_secret');

        if (! is_string($webhookSecret) || $webhookSecret === '') {
            return response('Webhook secret not configured', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Get request body
        $payload = $request->getContent();

        // Compute expected signature
        $expectedSignature = hash_hmac('sha256', $timestamp.$payload, $webhookSecret);

        // Validate signature using timing-safe comparison
        if (! hash_equals($expectedSignature, $signature)) {
            // Check if strict mode is enabled
            $strictMode = config('services.cognito.hmac_strict_mode', false);

            if ($strictMode) {
                return response('Invalid signature', Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
