<?php

namespace App\DTOs\Push;

use App\Enums\NotificationTypes;

class PushNotificationDTO
{
    public function __construct(
        public string $title,
        public string $body,
        public NotificationTypes $type,
        public ?string $notificationId = null,
        public ?string $url = null,
        public ?string $image = null,
        public ?string $icon = null,
        public array $data = [],
        public array $buttons = [],
        public string $priority = 'normal',
        public int $ttl = 86400,
        public array $recipientIds = [],
        public array $topicIds = [],
        public ?string $scheduledAt = null,
        public ?string $authorId = null,
    ) {}

    public static function from(array $data): self
    {
        return new self(
            title: $data['title'],
            body: $data['body'],
            type: NotificationTypes::fromValue($data['type']),
            notificationId: $data['notification_id'] ?? null,
            url: $data['url'] ?? null,
            image: $data['image'] ?? null,
            icon: $data['icon'] ?? null,
            data: $data['data'] ?? [],
            buttons: $data['buttons'] ?? [],
            priority: $data['priority'] ?? 'normal',
            ttl: $data['ttl'] ?? 86400,
            recipientIds: $data['recipient_ids'] ?? [],
            topicIds: $data['topic_ids'] ?? [],
            scheduledAt: $data['scheduled_at'] ?? null,
            authorId: $data['author_id'] ?? null,
        );
    }

    public function isScheduled(): bool
    {
        return $this->scheduledAt !== null;
    }

    public function toArray(): array
    {
        return [
            'notification_id' => $this->notificationId,
            'type' => $this->type->value,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'image' => $this->image,
            'icon' => $this->icon,
            'data' => $this->data,
            'buttons' => $this->buttons,
            'priority' => $this->priority,
            'ttl' => $this->ttl,
            'recipient_ids' => $this->recipientIds,
            'topic_ids' => $this->topicIds,
            'scheduled_at' => $this->scheduledAt,
            'author_id' => $this->authorId,
        ];
    }
}
