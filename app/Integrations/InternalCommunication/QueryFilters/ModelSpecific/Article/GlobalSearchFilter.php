<?php

namespace App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article;

use App\Integrations\InternalCommunication\Models\Article;
use Closure;
use Illuminate\Database\Eloquent\Builder;

class GlobalSearchFilter
{
    /**
     * Filters articles by searching across multiple fields (title, content, tags).
     * Handles both string and JSON fields.
     *
     * @param  Builder<Article>  $builder
     * @return Builder<Article>
     */
    public function handle(Builder $builder, Closure $next): Builder
    {
        $searchTerm = request('search');
        $language = request('language');

        // Use user's locale as default if no language is specified
        if (! $language && auth()->user()) {
            $language = auth()->user()->locale;
        }

        if (is_string($searchTerm) && $searchTerm !== '') {
            $builder->where(function ($mainQuery) use ($searchTerm, $language): void {
                // Search in translations (title and content)
                $mainQuery->whereHas('translations', function ($query) use ($searchTerm, $language): void {
                    // Apply language filter if provided
                    if ($language) {
                        $query->where('language', $language);
                    }

                    // Search in title and content
                    $query->where(function ($subQuery) use ($searchTerm): void {
                        $subQuery->where('title', 'ILIKE', '%'.$searchTerm.'%')
                            ->orWhereRaw('CAST(content AS TEXT) ILIKE ?', ['%'.$searchTerm.'%']);
                    });
                })
                // Also search in tags
                    ->orWhereHas('tags', function ($query) use ($searchTerm, $language): void {
                        $searchPattern = '%'.$searchTerm.'%';
                        // Search in the label JSON field for the user's language
                        if ($language) {
                            $query->whereRaw('LOWER(label->>?) LIKE LOWER(?)', [$language, $searchPattern]);
                        } else {
                            // Search in any language if no specific language
                            $query->whereRaw('LOWER(label::text) LIKE LOWER(?)', [$searchPattern]);
                        }
                    });
            });
        }

        return $next($builder);
    }
}
