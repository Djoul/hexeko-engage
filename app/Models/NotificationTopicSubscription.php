<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class NotificationTopicSubscription extends Pivot
{
    protected $table = 'notification_topic_subscriptions';

    protected $casts = [
        'subscribed_at' => 'datetime',
    ];
}
