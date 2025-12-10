<?php

namespace App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article;

use App\Integrations\InternalCommunication\Models\Article;
use Closure;
use Illuminate\Database\Eloquent\Builder;

class ContentFilter
{
    /**
     * Filters articles by content (partial search, case insensitive).
     */
    protected function getColumnName(): string
    {
        // Recherche dans la relation translations, colonne content
        return 'int_communication_rh_articles_translations.content';
    }

    /**
     * @param  Builder<Article>  $builder
     * @return Builder<Article>
     */
    public function handle(Builder $builder, Closure $next): Builder
    {
        $value = request('content');
        if (is_array($value) && $value !== []) {
            $value = json_encode($value);
        } elseif (is_string($value) && $value !== '') {
            // $value reste inchangé
        } else {
            $value = null;
        }
        if (is_string($value)) {
            $builder->whereHas('translations', function ($query) use ($value): void {
                $query->where('content', 'like', "%$value%");
            });
        }

        return $next($builder);
    }
}
