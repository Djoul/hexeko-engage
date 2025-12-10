<?php

namespace App\Repositories\Contracts;

use App\Models\Role;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

interface RoleRepositoryInterface
{
    /**
     * @param  array<string>  $relations
     * @return Collection<int, Role>
     */
    public function all(array $relations = []);

    /**
     * @param  string  $id
     * @param  array<string>  $relations
     * @return Role
     *
     * @throws ModelNotFoundException
     */
    public function find($id, array $relations = []);

    /**
     * @param  array<string,mixed>  $data
     * @return Role
     */
    public function create(array $data);

    /**
     * @param  array<string,mixed>  $data
     * @return Role
     */
    public function update(Role $role, array $data);

    /**
     * @return bool
     */
    public function delete(Role $role);
}
