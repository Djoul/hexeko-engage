<?php

namespace App\Listeners\Auth;

use App\Models\User;
use Spatie\Permission\Events\RoleDetached;

class RoleDetachedListener
{
    public function handle(RoleDetached $event): void
    {
        /** @var User $user */
        $user = $event->model;

        activity('User')
            ->performedOn($user)
            ->log('Role Detached');
    }
}
