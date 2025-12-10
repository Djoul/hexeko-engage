<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if broadcasting config already exists
        if (! File::exists(config_path('broadcasting.php'))) {
            // Install Reverb (will create config files)
            Artisan::call('reverb:install', [
                '--no-interaction' => true,
            ]);

            Log::info('Laravel Reverb has been installed successfully.');
        } else {
            Log::info('Broadcasting configuration already exists. Skipping Reverb installation.');
        }

        // Ensure the broadcast connection is set to reverb
        if (config('broadcasting.default') !== 'reverb') {
            Log::warning('Please update BROADCAST_CONNECTION=reverb in your .env file');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration doesn't create any database tables
        // Reverb configuration files will remain in place
        Log::info('Reverb configuration files have been left in place. Remove manually if needed.');
    }
};
