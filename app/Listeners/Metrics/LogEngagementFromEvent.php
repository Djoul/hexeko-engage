<?php

namespace App\Listeners\Metrics;

use App\Models\EngagementLog;
use Str;

class LogEngagementFromEvent
{
    public function handle(object $event): void
    {
        $target = match (true) {
            method_exists($event, 'getTarget') => $event->getTarget(),
            property_exists($event, 'module') => $event->module,
            property_exists($event, 'articleId') => $event->articleId,
            property_exists($event, 'target') => $event->target,
            class_basename($event) === 'OrderCreated' && property_exists($event, 'order') => 'voucher:amilon:'.$event->order->external_order_id,
            default => null
        };

        // Get the user ID from the event if it exists
        $userId = null;
        if (property_exists($event, 'userId')) {
            $userId = empty($event->userId) ? null : $event->userId;
        } elseif (property_exists($event, 'user_id')) {
            $userId = empty($event->user_id) ? null : $event->user_id;
        } elseif (method_exists($event, 'getUserId')) {
            $userId = empty($event->getUserId()) ? null : $event->getUserId();
        }

        // Get metadata for specific event types
        $metadata = null;
        if (class_basename($event) === 'OrderCreated' && property_exists($event, 'order')) {
            $metadata = [
                'amount' => $event->order->amount,
                'merchant_id' => $event->order->merchant_id,
            ];
        }

        EngagementLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'type' => class_basename($event),
            'target' => $target,
            'metadata' => $metadata,
            'logged_at' => now(),
        ]);

    }
}
