<?php

namespace App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article;

use App\Integrations\InternalCommunication\Models\Article;
use Carbon\Carbon;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Builder;

class PublishedFromFilter
{
    /**
     * @param  Builder<Article>  $builder
     * @return Builder<Article>
     */
    public function handle(Builder $builder, Closure $next): Builder
    {
        if (request()->has('published_from') && request('published_from') !== '') {
            try {
                $publishedFrom = request('published_from');
                if (is_string($publishedFrom)) {
                    $date = Carbon::parse($publishedFrom)->startOfDay();
                    $builder->where('published_at', '>=', $date);
                }
            } catch (Exception $e) {
                // Invalid date format, ignore filter
            }
        }

        return $next($builder);
    }
}
