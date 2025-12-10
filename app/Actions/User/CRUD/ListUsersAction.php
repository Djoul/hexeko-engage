<?php

declare(strict_types=1);

namespace App\Actions\User\CRUD;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Action to list users with filtering and relations
 *
 * This action provides programmatic access to filtered user lists.
 * For HTTP/API pagination, use UserIndexController which handles
 * cursor/offset pagination and HTTP-specific concerns.
 *
 * This action is useful for:
 * - Jobs that need to process all users
 * - Commands that need user lists
 * - Other non-HTTP contexts
 */
class ListUsersAction
{
    /**
     * Execute the list users action
     *
     * @param  array<string>  $relations  Relations to eager load
     * @param  bool  $applyFilters  Whether to apply QueryFilter pipeline (default: true)
     * @param  int|null  $limit  Optional limit on number of results
     * @return Collection<int, User> Collection of users
     */
    public function execute(
        array $relations = [],
        bool $applyFilters = true,
        ?int $limit = null
    ): Collection {
        // Build base query
        $query = $this->buildQuery($relations, $applyFilters);

        // Apply limit if specified
        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        /** @var Collection<int, User> */
        return $query->get();
    }

    /**
     * Build the base query with relations and filters
     *
     * @param  array<string>  $relations  Relations to eager load
     * @param  bool  $applyFilters  Whether to apply QueryFilter pipeline
     * @return Builder<User> Query builder instance
     */
    public function buildQuery(array $relations = [], bool $applyFilters = true): Builder
    {
        // Use default relations if none specified
        $relationsToLoad = $relations !== [] ? $relations : $this->getDefaultRelations();

        /** @var Builder<User> $query */
        $query = User::query()->with($relationsToLoad);

        // Apply QueryFilter pipeline if requested
        // This respects request parameters like status, financer_id, search, etc.
        if ($applyFilters) {
            /** @phpstan-ignore method.notFound */
            return $query->pipeFiltered();
        }

        return $query;
    }

    /**
     * Get default relations configuration
     *
     * @return array<string|int, mixed>
     */
    private function getDefaultRelations(): array
    {
        return [
            'media' => fn ($q) => $q->where('collection_name', 'profile_image'),
            'roles',
            'roles.permissions',
            'permissions',
            'financers',
        ];
    }

    /**
     * Count users matching current filters
     *
     * @param  bool  $applyFilters  Whether to apply QueryFilter pipeline
     * @return int Count of matching users
     */
    public function count(bool $applyFilters = true): int
    {
        $query = $this->buildQuery([], $applyFilters);

        return $query->count();
    }
}
