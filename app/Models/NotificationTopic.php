<?php

namespace App\Models;

use App\Models\Traits\HasDivisionThroughFinancer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NotificationTopic extends Model
{
    use HasDivisionThroughFinancer;
    use HasFactory,HasUuids;

    protected $casts = [
        'id' => 'string',
        'is_active' => 'boolean',
        'subscriber_count' => 'integer',
    ];

    public function financer(): BelongsTo
    {
        return $this->belongsTo(Financer::class);
    }

    public function subscriptions(): BelongsToMany
    {
        return $this->belongsToMany(
            PushSubscription::class,
            'notification_topic_subscriptions',
            'notification_topic_id',
            'push_subscription_id'
        )->using(NotificationTopicSubscription::class)
            ->withPivot('subscribed_at')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForFinancer($query, $financerId)
    {
        return $query->where('financer_id', $financerId);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('financer_id');
    }

    public function incrementSubscriberCount(): void
    {
        $this->increment('subscriber_count');
    }

    public function decrementSubscriberCount(): void
    {
        $this->decrement('subscriber_count');
    }

    public function updateSubscriberCount(): void
    {
        $count = $this->subscriptions()->count();
        $this->update(['subscriber_count' => $count]);
    }
}
