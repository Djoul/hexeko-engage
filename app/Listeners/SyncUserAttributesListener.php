<?php

namespace App\Listeners;

use App\Jobs\SyncUserAttributesJob;

class SyncUserAttributesListener
{
    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        $user = $event->invitedUser;
        $financerId = $event->financerId;

        if ($user && $user->sirh_id !== null && $financerId) {
            SyncUserAttributesJob::dispatch($user, $financerId);
        }
    }
}
