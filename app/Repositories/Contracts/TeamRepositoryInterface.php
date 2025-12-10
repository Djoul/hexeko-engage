<?php

namespace App\Repositories\Contracts;

use App\Models\Team;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

interface TeamRepositoryInterface
{
    /**
     * @param  array<string>  $relations
     * @return Collection<int, Team>
     */
    public function all(array $relations = []);

    /**
     * @param  string  $id
     * @param  array<string>  $relations
     * @return Team
     *
     * @throws ModelNotFoundException
     */
    public function find($id, array $relations = []);

    /**
     * @param  array<string,mixed>  $data
     * @return Team
     */
    public function create(array $data);

    /**
     * @param  array<string,mixed>  $data
     * @return Team
     */
    public function update(Team $team, array $data);

    /**
     * @return bool
     */
    public function delete(Team $team);
}
