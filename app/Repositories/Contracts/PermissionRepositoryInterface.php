<?php

namespace App\Repositories\Contracts;

use App\Models\Permission;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

interface PermissionRepositoryInterface
{
    /**
     * @param  array<string>  $relations
     * @return Collection<int, Permission>
     */
    public function all(array $relations = []);

    /**
     * @param  string  $id
     * @param  array<string>  $relations
     * @return Permission
     *
     * @throws ModelNotFoundException
     */
    public function find($id, array $relations = []);

    /**
     * @param  array<string,mixed>  $data
     * @return Permission
     */
    public function create(array $data);

    /**
     * @param  array<string,mixed>  $data
     * @return Permission
     */
    public function update(Permission $permission, array $data);

    /**
     * @return bool
     */
    public function delete(Permission $permission);
}
