<?php

namespace App\Models;

use App\Enums\NotificationTypes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PushNotification extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'type' => NotificationTypes::class,
        'data' => 'array',
        'buttons' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'recipient_count' => 'integer',
        'delivered_count' => 'integer',
        'opened_count' => 'integer',
        'clicked_count' => 'integer',
        'device_count' => 'integer',
        'ttl' => 'integer',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function pushEvents(): HasMany
    {
        return $this->hasMany(PushEvent::class);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now());
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeByType($query, NotificationTypes $type)
    {
        return $query->where('type', $type);
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function incrementDeliveryCount(): void
    {
        $this->increment('delivered_count');
    }

    public function incrementOpenCount(): void
    {
        $this->increment('opened_count');
    }

    public function incrementClickCount(): void
    {
        $this->increment('clicked_count');
    }

    public function getDeliveryRate(): float
    {
        if ($this->recipient_count === 0) {
            return 0;
        }

        return round(($this->delivered_count / $this->recipient_count) * 100, 2);
    }

    public function getOpenRate(): float
    {
        if ($this->delivered_count === 0) {
            return 0;
        }

        return round(($this->opened_count / $this->delivered_count) * 100, 2);
    }

    public function getClickRate(): float
    {
        if ($this->opened_count === 0) {
            return 0;
        }

        return round(($this->clicked_count / $this->opened_count) * 100, 2);
    }
}
