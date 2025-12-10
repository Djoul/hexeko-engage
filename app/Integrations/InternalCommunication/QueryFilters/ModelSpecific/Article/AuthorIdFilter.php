<?php

namespace App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article;

use App\Integrations\InternalCommunication\Models\Article;
use Closure;
use Illuminate\Database\Eloquent\Builder;

class AuthorIdFilter
{
    /**
     * @param  Builder<Article>  $builder
     * @return Builder<Article>
     */
    public function handle(Builder $builder, Closure $next): Builder
    {
        if (request()->has('author_id') && request('author_id') !== '') {
            $authorId = request('author_id');

            if (is_string($authorId)) {
                $builder->where('author_id', $authorId);
            }
        }

        return $next($builder);
    }
}
