<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('application.app_name', 'Up Engage API');
        $this->migrator->add('application.app_url', 'http://localhost:1310');
        $this->migrator->add('application.timezone', 'Europe/Brussels');
        $this->migrator->add('application.maintenance_mode', false);
        $this->migrator->add('application.maintenance_message', null);
    }
};
