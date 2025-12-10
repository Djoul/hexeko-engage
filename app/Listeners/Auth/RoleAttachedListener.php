<?php

namespace App\Listeners\Auth;

use App\Models\User;
use Spatie\Permission\Events\RoleAttached;

class RoleAttachedListener
{
    public function handle(RoleAttached $event): void
    {
        /** @var User $user */
        $user = $event->model;

        activity('User')
            ->performedOn($user)
            ->log('Role assigned');
    }
}
