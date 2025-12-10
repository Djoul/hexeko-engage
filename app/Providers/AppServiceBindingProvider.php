<?php

namespace App\Providers;

use App\Repositories\Auth\CognitoRepository;
use App\Repositories\Contracts\AuthRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceBindingProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(AuthRepositoryInterface::class, CognitoRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
