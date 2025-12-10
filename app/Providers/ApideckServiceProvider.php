<?php

namespace App\Providers;

use App\Services\Apideck\ApideckService;
use Illuminate\Support\ServiceProvider;

class ApideckServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(ApideckService::class, function ($app): ApideckService {
            return new ApideckService;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
