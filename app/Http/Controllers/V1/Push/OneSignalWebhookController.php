<?php

namespace App\Http\Controllers\V1\Push;

use App\Actions\Push\ProcessWebhookEventAction;
use App\DTOs\Push\PushEventDTO;
use App\Http\Controllers\Controller;
use App\Models\PushNotification;
use Dedoc\Scramble\Attributes\Group;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

#[Group('Notifications/Push')]
class OneSignalWebhookController extends Controller
{
    private const WEBHOOK_TIMEOUT_SECONDS = 300; // 5 minutes

    private const RATE_LIMIT_KEY = 'onesignal_webhook';

    private const RATE_LIMIT_MAX_ATTEMPTS = 3; // per minute for testing

    public function __construct(
        private readonly ProcessWebhookEventAction $processWebhookEventAction
    ) {}

    /**
     * Handle OneSignal webhook events
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Rate limiting
        if (! $this->checkRateLimit($request)) {
            return response()->json([
                'message' => 'Too many requests',
            ], 429);
        }

        // Validate signature
        if (! $this->validateSignature($request)) {
            $hasSignature = $request->hasHeader('X-OneSignal-Signature');
            $message = $hasSignature ? 'Invalid webhook signature' : 'Missing webhook signature';

            return $this->unauthorizedResponse($message);
        }

        // Validate timestamp to prevent replay attacks
        if (! $this->validateTimestamp($request)) {
            return $this->unauthorizedResponse('Webhook timestamp too old');
        }

        // Validate required payload structure
        $validation = $this->validatePayload($request);
        if ($validation !== true) {
            return response()->json([
                'message' => 'Invalid payload structure',
                'errors' => $validation,
            ], 422);
        }

        try {
            if ($request->has('events')) {
                return $this->handleBatchEvents($request);
            }

            return $this->handleSingleEvent($request);
        } catch (Exception $e) {
            Log::error('OneSignal webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }

    /**
     * Handle single webhook event
     */
    private function handleSingleEvent(Request $request): JsonResponse
    {
        $payload = $request->all();

        $notification = $this->findNotification($payload['id'] ?? null);

        if (! $notification instanceof PushNotification) {
            Log::warning('OneSignal webhook for unknown notification', [
                'external_id' => $payload['id'] ?? 'unknown',
                'event' => $payload['event'] ?? 'unknown',
            ]);

            return response()->json([
                'success' => false,
                'data' => [
                    'warning' => 'Notification not found',
                ],
            ], 404);
        }

        $dto = PushEventDTO::fromWebhookPayload($payload, $notification->id);
        $this->processWebhookEventAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => 'Webhook processed successfully',
        ]);
    }

    /**
     * Handle batch webhook events
     */
    private function handleBatchEvents(Request $request): JsonResponse
    {
        $events = $request->input('events', []);
        $processedCount = 0;

        foreach ($events as $eventPayload) {
            $notification = $this->findNotification($eventPayload['id'] ?? null);

            if (! $notification instanceof PushNotification) {
                Log::warning('OneSignal webhook for unknown notification in batch', [
                    'external_id' => $eventPayload['id'] ?? 'unknown',
                    'event' => $eventPayload['event'] ?? 'unknown',
                ]);

                continue;
            }

            try {
                $dto = PushEventDTO::fromWebhookPayload($eventPayload, $notification->id);
                $this->processWebhookEventAction->execute($dto);
                $processedCount++;
            } catch (Exception $e) {
                Log::error('Failed to process batch event', [
                    'event' => $eventPayload,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Batch webhook processed successfully',
            'data' => [
                'processed_events' => $processedCount,
                'total_events' => count($events),
            ],
        ]);
    }

    /**
     * Find notification by external ID
     */
    private function findNotification(?string $externalId): ?PushNotification
    {
        if (in_array($externalId, [null, '', '0'], true)) {
            return null;
        }

        return PushNotification::where('external_id', $externalId)->first();
    }

    /**
     * Validate webhook signature
     */
    private function validateSignature(Request $request): bool
    {
        $signature = $request->header('X-OneSignal-Signature');

        if (! $signature) {
            Log::warning('OneSignal webhook missing signature header');

            return false;
        }

        $payload = $request->getContent();
        $secret = config('onesignal.webhook_secret');

        if (! $secret) {
            Log::error('OneSignal webhook secret not configured');

            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Validate timestamp to prevent replay attacks
     */
    private function validateTimestamp(Request $request): bool
    {
        $timestamp = $request->input('timestamp');

        if (! $timestamp) {
            return true; // Allow requests without timestamp for backward compatibility
        }

        $currentTime = time();
        $timeDifference = abs($currentTime - $timestamp);

        return $timeDifference < self::WEBHOOK_TIMEOUT_SECONDS;
    }

    /**
     * Validate payload structure
     */
    private function validatePayload(Request $request): bool|array
    {
        $rules = [
            'event' => 'required_without:events|string|in:sent,delivered,opened,clicked,dismissed,failed',
            'id' => 'required_without:events|string',
            'events' => 'array',
            'events.*.event' => 'required_with:events|string|in:sent,delivered,opened,clicked,dismissed,failed',
            'events.*.id' => 'required_with:events|string',
        ];

        $validator = validator($request->all(), $rules);

        return $validator->passes() ? true : $validator->errors()->toArray();
    }

    /**
     * Check rate limiting
     */
    private function checkRateLimit(Request $request): bool
    {
        $key = self::RATE_LIMIT_KEY.':'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_MAX_ATTEMPTS)) {
            return false;
        }

        RateLimiter::hit($key, 60); // 1 minute window

        return true;
    }

    /**
     * Return unauthorized response
     */
    private function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 401);
    }
}
