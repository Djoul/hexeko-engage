<?php

namespace App\Models\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasCreator
{
    protected static function bootHasCreator(): void
    {
        static::creating(function ($model): void {
            if (auth()->check() && ! $model->created_by) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model): void {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function wasCreatedBy(?User $user): bool
    {
        return $user && $this->created_by === $user->id;
    }

    public function wasUpdatedBy(?User $user): bool
    {
        return $user && $this->updated_by === $user->id;
    }
}
