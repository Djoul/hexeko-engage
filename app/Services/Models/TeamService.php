<?php

declare(strict_types=1);

namespace App\Services\Models;

use App\Models\Team;
use App\Repositories\Models\TeamRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class TeamService
{
    public function __construct(protected TeamRepository $teamRepository) {}

    /**
     * @param  array<string>  $relations
     * @return Collection<int, Team>
     */
    public function all(array $relations = []): Collection
    {
        /** @var Collection<int, Team> $collection */
        $collection = Team::with($relations)->get();

        return $collection;
    }

    /**
     * @param  array<string>  $relations
     */
    public function find(string $id, array $relations = []): Team
    {
        $team = Team::with($relations)->find($id);

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
        return $this->teamRepository->create($data);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function update(Team $team, array $data): Team
    {
        return $this->teamRepository->update($team, $data);
    }

    public function delete(Team $team): bool
    {
        return $this->teamRepository->delete($team);
    }
}
