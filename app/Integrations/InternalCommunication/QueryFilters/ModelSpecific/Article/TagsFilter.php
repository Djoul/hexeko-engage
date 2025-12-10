<?php

namespace App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article;

use App\Integrations\InternalCommunication\Models\Article;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TagsFilter
{
    /**
     * @param  Builder<Article>  $builder
     * @return Builder<Article>
     */
    public function handle(Builder $builder, Closure $next): Builder
    {
        if (request()->has('tags') && ! empty(request('tags'))) {
            $tags = request('tags');

            // Handle comma-separated string format
            if (is_string($tags)) {
                // Split by comma and trim whitespace
                $tags = array_map('trim', explode(',', $tags));
            }

            if (is_array($tags)) {
                // Filter out empty values, non-strings, and invalid UUIDs
                $validTags = array_filter($tags, function ($tag): bool {
                    return is_string($tag) && $tag !== '' && Str::isUuid($tag);
                });

                if ($validTags !== []) {
                    // Articles must have ANY of the specified tags (OR condition using whereIn)
                    $builder->whereHas('tags', function ($query) use ($validTags): void {
                        $query->whereIn('int_communication_rh_tags.id', $validTags);
                    });
                }
            }
        }

        return $next($builder);
    }
}
