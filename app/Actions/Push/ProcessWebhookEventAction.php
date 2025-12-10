<?php

namespace App\Actions\Push;

use App\DTOs\Push\PushEventDTO;
use App\Enums\PushEventTypes;
use App\Models\PushEvent;
use App\Models\PushNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessWebhookEventAction
{
    /**
     * Execute webhook event processing
     */
    public function execute(PushEventDTO $dto): PushEvent
    {
        return DB::transaction(function () use ($dto): PushEvent {
            // Create event record
            $event = $this->createEventRecord($dto);

            // Update notification based on event type
            $this->updateNotificationMetrics($event);

            return $event;
        });
    }

    /**
     * Create event record in database
     */
    private function createEventRecord(PushEventDTO $dto): PushEvent
    {
        return PushEvent::create([
            'push_notification_id' => $dto->pushNotificationId,
            'push_subscription_id' => $dto->pushSubscriptionId,
            'event_type' => $dto->eventType->value,
            'event_id' => $dto->eventId,
            'event_data' => $dto->eventData,
            'ip_address' => $dto->ipAddress,
            'user_agent' => $dto->userAgent,
            'occurred_at' => $dto->occurredAt,
        ]);
    }

    /**
     * Update notification metrics based on event type
     */
    private function updateNotificationMetrics(PushEvent $event): void
    {
        $notification = PushNotification::find($event->push_notification_id);

        if (! $notification) {
            Log::warning('Push notification not found for webhook event', [
                'notification_id' => $event->push_notification_id,
                'event_type' => $event->event_type->value,
            ]);

            return;
        }

        switch ($event->event_type->value) {
            case PushEventTypes::SENT:
                $this->handleSentEvent($notification);
                break;

            case PushEventTypes::DELIVERED:
                $this->handleDeliveredEvent($notification);
                break;

            case PushEventTypes::OPENED:
                $this->handleOpenedEvent($notification);
                break;

            case PushEventTypes::CLICKED:
                $this->handleClickedEvent($notification);
                break;

            case PushEventTypes::FAILED:
                $this->handleFailedEvent($notification);
                break;

            case PushEventTypes::DISMISSED:
                // No specific action needed for dismissed events
                break;
        }
    }

    /**
     * Handle sent event
     */
    private function handleSentEvent(PushNotification $notification): void
    {
        if ($notification->status === 'sending') {
            $notification->update([
                'status' => 'sent',
                'sent_at' => $notification->sent_at ?? now(),
            ]);
        }
    }

    /**
     * Handle delivered event
     */
    private function handleDeliveredEvent(PushNotification $notification): void
    {
        $notification->incrementDeliveryCount();
    }

    /**
     * Handle opened event
     */
    private function handleOpenedEvent(PushNotification $notification): void
    {
        $notification->incrementOpenCount();
    }

    /**
     * Handle clicked event
     */
    private function handleClickedEvent(PushNotification $notification): void
    {
        $notification->incrementClickCount();
    }

    /**
     * Handle failed event
     */
    private function handleFailedEvent(PushNotification $notification): void
    {
        $notification->update([
            'status' => 'failed',
        ]);
    }
}
