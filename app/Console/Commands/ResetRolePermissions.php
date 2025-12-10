<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;

class ResetRolePermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-role-permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        Artisan::call('db:seed', ['--class' => 'DynamicPermissionSeeder']);
        Artisan::call('db:seed', ['--class' => 'DynamicRolePermissionSeeder']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info('Role permissions reset');
    }
}
