<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    Log::info('Running scheduler');
})->everyFiveMinutes();

Schedule::command('translations:dump-tables')->everyFourHours();

// Monitor Cognito notification failure rate hourly
Schedule::command('cognito:monitor-fallback')->hourly();
