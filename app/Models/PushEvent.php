<?php

namespace App\Models;

use App\Enums\PushEventTypes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushEvent extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'event_type' => PushEventTypes::class,
        'event_data' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function pushNotification(): BelongsTo
    {
        return $this->belongsTo(PushNotification::class);
    }

    public function pushSubscription(): BelongsTo
    {
        return $this->belongsTo(PushSubscription::class);
    }

    public function scopeByType($query, PushEventTypes $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeSuccessful($query)
    {
        return $query->whereIn('event_type', [
            PushEventTypes::SENT,
            PushEventTypes::DELIVERED,
            PushEventTypes::OPENED,
            PushEventTypes::CLICKED,
        ]);
    }

    public function scopeFailed($query)
    {
        return $query->where('event_type', PushEventTypes::FAILED);
    }

    public function scopeEngagement($query)
    {
        return $query->whereIn('event_type', [
            PushEventTypes::OPENED,
            PushEventTypes::CLICKED,
        ]);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('occurred_at', [$startDate, $endDate]);
    }

    public function isSuccessful(): bool
    {
        return $this->event_type->isSuccessful();
    }

    public function isEngagement(): bool
    {
        return $this->event_type->isEngagement();
    }
}
