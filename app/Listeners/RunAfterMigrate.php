<?php

namespace App\Listeners;

use App;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Artisan;

class RunAfterMigrate
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(MigrationsEnded $event): void
    {

        Artisan::call('app:reset-role-permissions');

        if (App::environment() !== 'local') {
            return;
        }

        Artisan::call('ide-helper:generate');
        Artisan::call('ide-helper:models', ['--nowrite' => true, '--silent' => true]);
        Artisan::call('ide-helper:meta');

    }
}
