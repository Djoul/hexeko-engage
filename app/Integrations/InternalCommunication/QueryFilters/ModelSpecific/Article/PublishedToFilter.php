<?php

namespace App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article;

use App\Integrations\InternalCommunication\Models\Article;
use Carbon\Carbon;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Builder;

class PublishedToFilter
{
    /**
     * @param  Builder<Article>  $builder
     * @return Builder<Article>
     */
    public function handle(Builder $builder, Closure $next): Builder
    {
        if (request()->has('published_to') && request('published_to') !== '') {
            try {
                $publishedTo = request('published_to');
                if (is_string($publishedTo)) {
                    $date = Carbon::parse($publishedTo)->endOfDay();
                    $builder->where('published_at', '<=', $date);
                }
            } catch (Exception $e) {
                // Invalid date format, ignore filter
            }
        }

        return $next($builder);
    }
}
