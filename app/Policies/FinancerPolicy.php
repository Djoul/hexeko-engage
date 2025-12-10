<?php

namespace App\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Enums\IDP\RoleDefaults;
use App\Models\Financer;
use App\Models\User;

class FinancerPolicy
{
    public function view(User $user, Financer $financer): bool
    {
        // Si l'utilisateur a read_any_financer, accès complet sans restriction
        if ($user->can(PermissionDefaults::READ_ANY_FINANCER)) {
            return true;
        }

        // Si l'utilisateur a read_own_financer, vérifier qu'il est rattaché au financer
        if ($user->can(PermissionDefaults::READ_OWN_FINANCER)) {
            // GOD/HEXEKO : vérifier que le financer est dans le contexte accessible
            if ($user->hasAnyRole([
                RoleDefaults::GOD,
                RoleDefaults::HEXEKO_SUPER_ADMIN,
                RoleDefaults::HEXEKO_ADMIN,
            ])) {
                return authorizationContext()->canAccessFinancer($financer->id);
            }

            // Utilisateurs normaux : vérifier la relation financer_user
            return $user->financers()
                ->where('financer_id', $financer->id)
                ->where('financer_user.active', true)
                ->exists();
        }

        return false;
    }

    public function manage(User $user, Financer $financer): bool
    {
        // GOD/HEXEKO : vérifier que le financer est dans le contexte accessible
        if ($user->hasAnyRole([
            RoleDefaults::GOD,
            RoleDefaults::HEXEKO_SUPER_ADMIN,
            RoleDefaults::HEXEKO_ADMIN,
        ])) {
            return authorizationContext()->canAccessFinancer($financer->id);
        }

        // Utilisateurs normaux : vérifier la relation financer_user
        return $user->financers()
            ->where('financer_id', $financer->id)
            ->where('financer_user.active', true)
            ->exists();
    }
}
