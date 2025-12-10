<?php

declare(strict_types=1);

namespace App\Services\Models;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Models\RoleRepository;
use Auth;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class RoleService
{
    public function __construct(protected RoleRepository $roleRepository) {}

    /**
     * @param  array<string>  $relations
     * @return Collection<int, Role>
     */
    public function all(array $relations = [])
    {
        return $this->roleRepository->all($relations);
    }

    /**
     * @param  string  $id
     * @param  array<string>  $relations
     * @return Role
     */
    public function find($id, array $relations = [])
    {
        return $this->roleRepository->find($id, $relations);
    }

    /**
     * @param  array<string>  $relations
     * @return Role
     */
    public function findByName(string $name, string $teamId, array $relations = [])
    {
        return $this->roleRepository->findByName($name, $teamId, $relations);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function create(array $data): Role
    {
        return $this->roleRepository->create($data);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function update(Role $role, array $data): Role
    {
        if ($role->is_protected) {
            throw new UnprocessableEntityHttpException('You do not have permission to modify this role');
        }

        return $this->roleRepository->update($role, $data);
    }

    public function delete(Role $role): bool
    {
        if ($role->is_protected) {
            throw new UnprocessableEntityHttpException('You do not have permission to modify this role');
        }

        if (User::role($role)->count()) {
            throw new UnprocessableEntityHttpException('You do not have permission to modify this role, it is in use by any user');
        }

        return $this->roleRepository->delete($role);
    }

    public function addPermission(Role $role, Permission $permission): Role
    {
        if (Auth::check() && Auth::user()?->hasAnyRole($role->name)) {
            abort(403, 'You do not have permission to modify you own role');
        }

        $role->canBeModifiedByAuth();
        $role->givePermissionTo($permission->name);

        return $role;
    }

    public function removePermission(Role $role, Permission $permission): Role
    {
        $role->canBeModifiedByAuth();

        $role->revokePermissionTo($permission->name);

        return $role;
    }
}
