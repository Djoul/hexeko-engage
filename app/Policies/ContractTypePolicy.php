<?php

namespace App\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Models\ContractType;
use App\Models\User;

class ContractTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::READ_CONTRACT_TYPE);
    }

    public function view(User $user, ContractType $contractType): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::READ_CONTRACT_TYPE)) {
            return $user->current_financer_id === $contractType->financer_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::CREATE_CONTRACT_TYPE);
    }

    public function update(User $user, ContractType $contractType): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::UPDATE_CONTRACT_TYPE)) {
            return $user->current_financer_id === $contractType->financer_id;
        }

        return false;
    }

    public function delete(User $user, ContractType $contractType): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::DELETE_CONTRACT_TYPE)) {
            return $user->current_financer_id === $contractType->financer_id;
        }

        return false;
    }
}
