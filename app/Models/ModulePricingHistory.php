<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\MediaCollections\Models\Concerns\HasUuid;

class ModulePricingHistory extends Model
{
    use HasFactory, HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'module_pricing_history';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'old_price' => 'integer',
        'new_price' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    /**
     * Get the module associated with this pricing history.
     *
     * @return BelongsTo<Module, ModulePricingHistory>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who changed the price.
     *
     * @return BelongsTo<User, ModulePricingHistory>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Scope to get active pricing for a given date.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActiveOn(Builder $query, mixed $date): Builder
    {
        return $query->where('valid_from', '<=', $date)
            ->where(function ($q) use ($date): void {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', $date);
            });
    }

    /**
     * Scope to get current active pricing.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->activeOn(now());
    }

    /**
     * Scope for a specific entity.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForEntity(Builder $query, string $entityId, string $entityType): Builder
    {
        return $query->where('entity_id', $entityId)
            ->where('entity_type', $entityType);
    }
}
