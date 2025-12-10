<?php

namespace App\Actions\Push;

use App\DTOs\Push\PushNotificationDTO;
use App\Jobs\Push\SendPushNotificationJob;
use App\Models\PushNotification;
use App\Models\PushSubscription;
use App\Services\OneSignalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SendPushNotificationAction
{
    public function __construct(
        private readonly OneSignalService $oneSignalService
    ) {}

    /**
     * Execute sending push notification
     */
    public function execute(PushNotificationDTO $dto): PushNotification
    {
        return DB::transaction(function () use ($dto) {
            // Create notification record
            $notification = $this->createNotificationRecord($dto);

            // Handle scheduling
            if (! in_array($dto->scheduledAt, [null, '', '0'], true)) {
                $this->scheduleNotification($notification, $dto);
            } else {
                $this->sendImmediately($notification, $dto);
            }

            return $notification->fresh();
        });
    }

    /**
     * Create notification record in database
     */
    private function createNotificationRecord(PushNotificationDTO $dto): PushNotification
    {
        $deviceIds = [];
        $recipientCount = 0;

        // Determine delivery type and count recipients
        if ($dto->recipientIds === []) {
            // Broadcast to all
            $deliveryType = 'broadcast';
            $recipientCount = PushSubscription::where('push_enabled', true)->count();
        } else {
            // Targeted delivery
            $deliveryType = 'targeted';
            $subscriptions = PushSubscription::whereIn('user_id', $dto->recipientIds)
                ->where('push_enabled', true)
                ->get();

            $deviceIds = $subscriptions->pluck('subscription_id')->toArray();
            $recipientCount = $subscriptions->unique('user_id')->count();
        }

        return PushNotification::create([
            'notification_id' => Str::uuid()->toString(),
            'type' => $dto->type->value,
            'delivery_type' => $deliveryType,
            'title' => $dto->title,
            'body' => $dto->body,
            'url' => $dto->url,
            'image' => $dto->image,
            'icon' => $dto->icon,
            'data' => $dto->data ?? [],
            'buttons' => $dto->buttons ?? [],
            'priority' => $dto->priority ?? 'normal',
            'ttl' => $dto->ttl ?? 86400,
            'status' => in_array($dto->scheduledAt, [null, '', '0'], true) ? 'sending' : 'scheduled',
            'recipient_count' => $recipientCount,
            'device_count' => count($deviceIds),
            'scheduled_at' => $dto->scheduledAt,
            'author_id' => $dto->authorId,
        ]);
    }

    /**
     * Schedule notification for future delivery
     */
    private function scheduleNotification(PushNotification $notification, PushNotificationDTO $dto): void
    {
        // Queue the job for scheduled time
        SendPushNotificationJob::dispatch($notification)
            ->delay($dto->scheduledAt);
    }

    /**
     * Send notification immediately
     */
    private function sendImmediately(PushNotification $notification, PushNotificationDTO $dto): void
    {
        // Build OneSignal notification payload
        $payload = $this->buildOneSignalPayload($dto);

        // Send based on delivery type
        if ($dto->recipientIds === []) {
            // Broadcast - check if there are any subscriptions first
            $subscriptionCount = PushSubscription::where('push_enabled', true)->count();

            if ($subscriptionCount === 0) {
                $notification->update([
                    'status' => 'failed',
                    'recipient_count' => 0,
                    'device_count' => 0,
                    'data' => array_merge($notification->data ?? [], [
                        'error' => 'No active push subscriptions found',
                    ]),
                ]);

                return;
            }

            $response = $this->oneSignalService->broadcast($payload);
        } else {
            // Get device IDs
            $deviceIds = PushSubscription::whereIn('user_id', $dto->recipientIds)
                ->where('push_enabled', true)
                ->pluck('subscription_id')
                ->toArray();

            if (! empty($deviceIds)) {
                $response = $this->oneSignalService->sendToUsers($payload, $deviceIds);
            } else {
                // No valid devices
                $notification->update([
                    'status' => 'failed',
                    'recipient_count' => 0,
                    'device_count' => 0,
                    'data' => array_merge($notification->data ?? [], [
                        'error' => 'No active push subscriptions for targeted users',
                    ]),
                ]);

                return;
            }
        }

        // Update notification with response
        $notification->update([
            'external_id' => $response['id'] ?? null,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Build OneSignal notification payload
     */
    private function buildOneSignalPayload(PushNotificationDTO $dto): array
    {
        $payload = [
            'headings' => ['en' => $dto->title],
            'contents' => ['en' => $dto->body],
        ];

        // Only add data if not empty (OneSignal requires null or valid object)
        if ($dto->data !== []) {
            $payload['data'] = $dto->data;
        }

        // Add priority mapping
        if ($dto->priority !== '' && $dto->priority !== '0') {
            $payload['priority'] = $this->mapPriority($dto->priority);
        }

        // Add URL if present
        if (! in_array($dto->url, [null, '', '0'], true)) {
            $payload['url'] = $dto->url;
        }

        // Add image if present
        if (! in_array($dto->image, [null, '', '0'], true)) {
            $payload['big_picture'] = $dto->image;
            $payload['chrome_web_image'] = $dto->image;
        }

        // Add icon if present
        if (! in_array($dto->icon, [null, '', '0'], true)) {
            $payload['chrome_web_icon'] = $dto->icon;
            $payload['firefox_icon'] = $dto->icon;
        }

        // Add buttons if present
        if ($dto->buttons !== []) {
            $payload['buttons'] = $this->formatButtons($dto->buttons);
        }

        // Add TTL
        if ($dto->ttl !== 0) {
            $payload['ttl'] = $dto->ttl;
        }

        return $payload;
    }

    /**
     * Map priority to OneSignal values
     */
    private function mapPriority(string $priority): int
    {
        return match ($priority) {
            'urgent' => 10,
            'high' => 10,
            'normal' => 5,
            'low' => 1,
            default => 5,
        };
    }

    /**
     * Format buttons for OneSignal
     */
    private function formatButtons(array $buttons): array
    {
        return array_map(function (array $button): array {
            $formatted = [
                'id' => $button['id'] ?? Str::random(8),
                'text' => $button['text'],
            ];

            if (isset($button['url'])) {
                $formatted['url'] = $button['url'];
            }

            return $formatted;
        }, $buttons);
    }
}
