<?php

namespace App\Http\Resources\Push;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'notification_id' => $this->notification_id,
            'external_id' => $this->external_id,
            'type' => $this->type,
            'delivery_type' => $this->delivery_type,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'image' => $this->image,
            'icon' => $this->icon,
            'data' => $this->data ?? [],
            'buttons' => $this->buttons ?? [],
            'priority' => $this->priority,
            'ttl' => $this->ttl,
            'status' => $this->status,
            'recipient_count' => $this->recipient_count,
            'device_count' => $this->device_count,
            'delivered_count' => $this->delivered_count,
            'opened_count' => $this->opened_count,
            'clicked_count' => $this->clicked_count,
            'delivery_rate' => $this->getDeliveryRate(),
            'open_rate' => $this->getOpenRate(),
            'click_rate' => $this->getClickRate(),
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'author_id' => $this->author_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
