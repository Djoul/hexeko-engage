<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Services;

use App\Integrations\InternalCommunication\Models\Tag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Tag Service
 *
 * Note: All queries are automatically filtered by financer_id via HasFinancer global scope.
 */
class TagService
{
    /**
     * Get all tags with pagination.
     *
     * Note: Tags are automatically filtered by HasFinancer global scope.
     *
     * @param  array<string>  $relations
     * @return Collection<int, Tag>
     */
    public function all(int $perPage = 15, int $page = 1, array $relations = []): Collection
    {
        return Tag::with([...$relations, 'financer'])
            ->get()
            ->forPage($page, $perPage);
        /* todo refactore pagination on query instead of collection */
    }

    /**
     * Find a tag by ID.
     *
     * Note: Tag must belong to current financer (HasFinancer global scope).
     *
     * @param  array<string>  $relations
     */
    public function find(string $id, array $relations = []): Tag
    {
        /** @var Tag $tag */
        $tag = Tag::with($relations)->findOrFail($id);

        return $tag;
    }

    /**
     * Find tags by financer ID.
     *
     * Note: This method is redundant since HasFinancer global scope auto-filters.
     * Consider using all() instead.
     *
     * @deprecated Use all() instead - HasFinancer scope handles filtering
     *
     * @param  array<string>  $relations
     * @return Collection<int, Tag>
     */
    public function findByFinancer(string $financerId, array $relations = []): Collection
    {
        /** @var Collection<int, Tag> $tags */
        $tags = Tag::with($relations)
            ->where('financer_id', $financerId)
            ->orderBy('created_at')
            ->get();

        return $tags;
    }

    /**
     * Create a new tag.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Tag
    {
        /** @var Tag $tag */
        $tag = Tag::create($data);

        return $tag;
    }

    /**
     * Update a tag.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Tag|Model $tag, array $data): Tag
    {
        $tag->update($data);

        /** @var Tag $tag */
        return $tag;
    }

    /**
     * Delete a tag.
     */
    public function delete(Tag|Model $tag): bool
    {
        $result = $tag->delete();

        return $result === null ? false : $result;
    }
}
