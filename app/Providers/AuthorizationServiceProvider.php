<?php

declare(strict_types=1);

namespace App\Providers;

use App\Security\AuthorizationContext;
use Illuminate\Support\ServiceProvider;

final class AuthorizationServiceProvider extends ServiceProvider
{
    /**
     * Register authorization services
     */
    public function register(): void
    {
        // Register AuthorizationContext as a scoped (request-scoped) singleton
        // This ensures one instance per request, avoiding data leakage between requests
        $this->app->scoped(AuthorizationContext::class, fn (): AuthorizationContext => new AuthorizationContext);
    }
}
