<?php

declare(strict_types=1);

namespace App\Services\Models;

use App\Enums\IDP\RoleDefaults;
use App\Models\Permission;
use App\Repositories\Models\PermissionRepository;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PermissionService
{
    public function __construct(protected PermissionRepository $permissionRepository) {}

    /**
     * @param  array<string>  $relations
     * @return Collection<int, Permission>
     */
    public function all(array $relations = [])
    {
        return $this->permissionRepository->all($relations);
    }

    /**
     * @param  string  $id
     * @param  array<string>  $relations
     * @return Permission
     */
    public function find($id, array $relations = [])
    {
        return $this->permissionRepository->find($id, $relations);
    }

    /**
     * @param  array<string,mixed>  $data
     * @return Permission
     */
    public function create(array $data)
    {
        return $this->permissionRepository->create($data);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function update(Permission $permission, array $data): Permission
    {
        if ($permission->is_protected && ! Auth()->user()?->hasRole(RoleDefaults::HEXEKO_SUPER_ADMIN)) {
            throw new UnprocessableEntityHttpException('You do not have permission to modify this role');
        }

        return $this->permissionRepository->update($permission, $data);
    }

    public function delete(Permission $permission): bool
    {
        if ($permission->is_protected && ! Auth()->user()?->hasRole(RoleDefaults::HEXEKO_SUPER_ADMIN)) {
            throw new UnprocessableEntityHttpException('You do not have permission to modify this role');
        }

        return $this->permissionRepository->delete($permission);
    }
}
