<?php

namespace App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article;

use App\Integrations\InternalCommunication\Models\Article;
use Closure;
use Illuminate\Database\Eloquent\Builder;

class TitleFilter
{
    /**
     * Filters articles by title (partial search, case insensitive).
     */
    protected function getColumnName(): string
    {
        // Recherche dans la relation translations, colonne title
        return 'translations.title';
    }

    /**
     * @param  Builder<Article>  $builder
     * @return Builder<Article>
     */
    public function handle(Builder $builder, Closure $next): Builder
    {
        $value = request()->input('title');
        if (is_string($value) && $value !== '') {
            $builder->whereHas('translations', function ($query) use ($value): void {
                $query->where('int_communication_rh_article_translations.title', 'LIKE', '%'.$value.'%');
            });
        }

        return $next($builder);
    }
}
