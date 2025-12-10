<?php

namespace App\Models;

use App\Enums\DeviceTypes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PushSubscription extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $casts = [
        'id' => 'string',
        'device_type' => DeviceTypes::class,
        'push_enabled' => 'boolean',
        'sound_enabled' => 'boolean',
        'vibration_enabled' => 'boolean',
        'notification_preferences' => 'array',
        'tags' => 'array',
        'metadata' => 'array',
        'last_active_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pushEvents(): HasMany
    {
        return $this->hasMany(PushEvent::class);
    }

    public function topics()
    {
        return $this->belongsToMany(
            NotificationTopic::class,
            'notification_topic_subscriptions',
            'push_subscription_id',
            'notification_topic_id'
        )->withPivot('subscribed_at');
    }

    public function scopeActive($query)
    {
        return $query->where('push_enabled', true);
    }

    public function scopeByDeviceType($query, DeviceTypes $type)
    {
        return $query->where('device_type', $type);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function markAsActive(): void
    {
        $this->update(['last_active_at' => now()]);
    }
}
