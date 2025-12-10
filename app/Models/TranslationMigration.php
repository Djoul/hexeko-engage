<?php

declare(strict_types=1);

namespace App\Models;

use App\Attributes\GlobalScopedModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[GlobalScopedModel]
class TranslationMigration extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected int $cacheTtl = 300; // 5 minutes

    protected $casts = [
        'metadata' => 'array',
        'executed_at' => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    /**
     * Scope for pending migrations
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for completed migrations
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for migrations by interface origin
     */
    public function scopeForInterface(Builder $query, string $interface): Builder
    {
        return $query->where('interface_origin', $interface);
    }

    /**
     * Get the S3 path for this migration file
     */
    public function getS3Path(): string
    {
        return sprintf('migrations/%s/%s', $this->interface_origin, $this->filename);
    }

    /**
     * Get the latest completed migration for a specific interface
     */
    public static function latestCompletedForInterface(string $interface): ?self
    {
        return static::forInterface($interface)
            ->completed()
            ->orderByDesc('executed_at')
            ->first();
    }

    /**
     * Check if this migration is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if this migration is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if this migration has failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Mark migration as processing
     */
    public function markAsProcessing(): self
    {
        $this->update(['status' => 'processing']);

        return $this;
    }

    /**
     * Mark migration as completed
     */
    public function markAsCompleted(): self
    {
        $this->update([
            'status' => 'completed',
            'executed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark migration as failed
     */
    public function markAsFailed(): self
    {
        $this->update([
            'status' => 'failed',
        ]);

        return $this;
    }

    /**
     * Mark migration as rolled back
     */
    public function markAsRolledBack(): self
    {
        $this->update([
            'status' => 'rolled_back',
            'rolled_back_at' => now(),
        ]);

        return $this;
    }
}
