<?php

namespace App\DTOs\Push;

use App\Enums\PushEventTypes;
use Carbon\Carbon;

class PushEventDTO
{
    public function __construct(
        public string $pushNotificationId,
        public PushEventTypes $eventType,
        public ?string $pushSubscriptionId = null,
        public ?string $eventId = null,
        public array $eventData = [],
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?Carbon $occurredAt = null,
    ) {
        $this->occurredAt ??= Carbon::now();
    }

    public static function from(array $data): self
    {
        return new self(
            pushNotificationId: $data['push_notification_id'],
            eventType: PushEventTypes::fromValue($data['event_type']),
            pushSubscriptionId: $data['push_subscription_id'] ?? null,
            eventId: $data['event_id'] ?? null,
            eventData: $data['event_data'] ?? [],
            ipAddress: $data['ip_address'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            occurredAt: isset($data['occurred_at']) ?
                (is_string($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : $data['occurred_at']) :
                Carbon::now(),
        );
    }

    public static function fromWebhookPayload(array $payload, string $notificationId): self
    {
        return new self(
            pushNotificationId: $notificationId,
            eventType: PushEventTypes::fromValue($payload['event']),
            pushSubscriptionId: null,
            eventId: $payload['external_id'] ?? null,
            eventData: [
                'custom_data' => $payload['custom_data'] ?? [],
            ],
            ipAddress: null,
            userAgent: null,
            occurredAt: isset($payload['timestamp']) ?
                Carbon::createFromTimestamp($payload['timestamp']) :
                Carbon::now(),
        );
    }

    public function toArray(): array
    {
        return [
            'push_notification_id' => $this->pushNotificationId,
            'push_subscription_id' => $this->pushSubscriptionId,
            'event_type' => $this->eventType->value,
            'event_id' => $this->eventId,
            'event_data' => $this->eventData,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'occurred_at' => $this->occurredAt?->toDateTimeString(),
        ];
    }
}
