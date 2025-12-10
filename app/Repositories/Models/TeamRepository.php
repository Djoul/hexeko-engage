<?php

declare(strict_types=1);

namespace App\Repositories\Models;

use App\Models\Team;
use App\Repositories\Contracts\TeamRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class TeamRepository implements TeamRepositoryInterface
{
    /**
     * @param  array<string>  $relations
     * @return Collection<int, Team>
     */
    public function all(array $relations = []): Collection
    {
        // Direct database access, no caching logic here
        /** @var Collection<int, Team> */
        return Team::with($relations)->get();
    }

    /**
     * @param  string  $id
     * @param  array<string>  $relations
     * @return Team
     *
     * @throws ModelNotFoundException
     */
    public function find($id, array $relations = [])
    {
        // Direct database access, no caching logic here
        $team = Team::with($relations)
            ->where('id', $id)
            ->first();

        if (! $team instanceof Team) {
            throw new ModelNotFoundException('Team not found');
        }

        return $team;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return Team
     */
    public function create(array $data)
    {
        // Create a new team in the database
        return Team::create($data);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function update(Team $team, array $data): Team
    {
        // Update the team with the provided data
        $team->update($data);

        return $team;
    }

    public function delete(Team $team): bool
    {
        // Delete the team from the database
        return (bool) $team->delete();
    }
}
