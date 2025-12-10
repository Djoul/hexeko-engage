<?php

namespace App\Models\Traits;

use App\Models\Financer;
use App\Scopes\HasNullableFinancerScope;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

trait HasNullableFinancer
{
    use HasDivisionScopes;
    use HasDivisionThroughFinancer;

    public static function bootHasNullableFinancer(): void
    {
        static::addGlobalScope(new HasNullableFinancerScope);
    }

    #[Scope]
    public function forFinancer(Builder $query, string $financerId): Builder
    {
        return $query->where(function ($q) use ($financerId): void {
            $q->whereNull('financer_id')
                ->orWhere('financer_id', $financerId);
        });
    }

    public function financer()
    {
        return $this->belongsTo(Financer::class);
    }
}
