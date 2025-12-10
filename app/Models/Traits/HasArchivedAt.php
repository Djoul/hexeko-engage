<?php

namespace App\Models\Traits;

use App\Scopes\HasArchivedAtScope;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

trait HasArchivedAt
{
    public static function bootHasArchivedAt(): void
    {
        $route = request()->route();
        $routeName = $route?->getName();

        if ($routeName === null || ($routeName !== 'survey.surveys.destroy' && $routeName !== 'survey.surveys.show')) {
            static::addGlobalScope(new HasArchivedAtScope);
        }
    }

    #[Scope]
    public function withArchived(Builder $query): Builder
    {
        return $query->withoutGlobalScope(HasArchivedAtScope::class);
    }

    #[Scope]
    public function withoutArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    #[Scope]
    public function onlyArchived(Builder $query): Builder
    {
        return $query->withoutGlobalScope(HasArchivedAtScope::class)->whereNotNull('archived_at');
    }
}
