<?php

declare(strict_types=1);

namespace App\Repositories\Models;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class RoleRepository implements RoleRepositoryInterface
{
    /**
     * @param  array<string>  $relations
     * @return Collection<int, Role>
     */
    public function all(array $relations = [])
    {
        return Role::with($relations)
            ->get();
    }

    /**
     * @param  string  $id
     * @param  array<string>  $relations
     * @return Role
     *
     * @throws ModelNotFoundException
     */
    public function find($id, array $relations = [])
    {
        $role = Role::with($relations)
            ->where('id', $id)
            ->first();

        if (! $role instanceof Role) {
            throw new ModelNotFoundException('Role not found');
        }

        return $role;
    }

    /**
     * @param  array<string>  $relations
     * @return Role
     *
     * @throws ModelNotFoundException
     */
    public function findByName(string $name, string $teamId, array $relations = [])
    {
        $role = Role::with($relations)
            ->whereTeamId($teamId)
            ->whereName($name)
            ->first();

        if (! $role instanceof Role) {
            throw new ModelNotFoundException('Role not found');
        }

        return $role;
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function create(array $data): Role
    {
        return Role::create($data);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function update(Role $role, array $data): Role
    {
        $role->update($data);

        return $role;
    }

    public function delete(Role $role): bool
    {
        return (bool) $role->delete();
    }
}
