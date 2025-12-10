<?php

namespace App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\InternalCommunication\Enums\StatusArticleEnum;
use App\Integrations\InternalCommunication\Models\Article;
use Closure;
use Illuminate\Database\Eloquent\Builder;

class StatusFilter
{
    /**
     * Filter articles by translation status.
     *
     * By default, users without VIEW_DRAFT_ARTICLE permission see only PUBLISHED translations.
     * Users with the permission can see both DRAFT and PUBLISHED.
     *
     * @param  Builder<Article>  $builder
     * @return Builder<Article>
     */
    public function handle(Builder $builder, Closure $next): Builder
    {
        $user = auth()->user();
        $status = request('status');
        $language = request('language');

        // Apply default filtering if user lacks VIEW_DRAFT_ARTICLE permission
        if (! $status && $user && ! $user->hasPermissionTo(PermissionDefaults::VIEW_DRAFT_ARTICLE)) {
            // Default: show ONLY published translations
            $status = StatusArticleEnum::PUBLISHED;
        }

        // Apply status filter if status is specified (or set by default)
        if ($status && StatusArticleEnum::hasValue($status)) {
            // Use user's locale as default if no language is specified
            if (! $language && $user) {
                $language = $user->locale;
            }

            $builder->whereHas('translations', function ($query) use ($status, $language): void {
                if ($language) {
                    $query->where('language', $language);
                }
                $query->where('status', $status);
            });
        }

        return $next($builder);
    }
}
