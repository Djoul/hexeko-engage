<?php

namespace App\Models\Traits;

use App\Models\Financer;
use App\Scopes\HasFinancerScope;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

trait HasFinancer
{
    use HasDivisionScopes;
    use HasDivisionThroughFinancer;

    public static function bootHasFinancer(): void
    {
        static::addGlobalScope(new HasFinancerScope);
    }

    #[Scope]
    public function forFinancer(Builder $query, string $financerId): Builder
    {
        return $query->where('financer_id', $financerId);
    }

    public function financer()
    {
        return $this->belongsTo(Financer::class);
    }
}
