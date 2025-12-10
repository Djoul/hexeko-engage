<?php

namespace App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article;

use App\Integrations\InternalCommunication\Models\Article;
use Auth;
use Closure;
use Illuminate\Database\Eloquent\Builder;

class SegmentIdFilter
{
    /**
     * Filter articles by segment_id.
     *
     * @param  Builder<Article>  $builder
     * @return Builder<Article>
     */
    public function handle(Builder $builder, Closure $next): Builder
    {

        if (request()->has('segment_id') && request('segment_id') !== '') {
            $builder->where('segment_id', request('segment_id'));
        }

        $user = Auth::user();
        if (request()->has('segmented') && request('segmented')) {
            $builder->where(function ($q) use ($user): void {
                $q->whereNull('segment_id')
                    ->orWhereIn('segment_id', $user->segments->pluck('id')->toArray());
            });
        }

        return $next($builder);
    }
}
