<?php

declare(strict_types=1);

namespace App\Repositories\Models;

use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class PermissionRepository implements PermissionRepositoryInterface
{
    /**
     * @param  array<string>  $relations
     * @return Collection<int, Permission>
     */
    public function all(array $relations = [])
    {
        return Permission::with($relations)
            ->get();
    }

    /**
     * @param  string  $id
     * @param  array<string>  $relations
     * @return Permission
     *
     * @throws ModelNotFoundException
     */
    public function find($id, array $relations = [])
    {
        $permission = Permission::with($relations)
            ->where('id', $id)
            ->first();

        if (! $permission instanceof Permission) {
            throw new ModelNotFoundException('Permission not found');
        }

        return $permission;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return Permission
     */
    public function create(array $data)
    {
        return Permission::create($data);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function update(Permission $permission, array $data): Permission
    {
        $permission->update($data);

        return $permission;
    }

    public function delete(Permission $permission): bool
    {
        return (bool) $permission->delete();
    }
}
