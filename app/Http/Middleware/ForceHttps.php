<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use URL;

class ForceHttps
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Force HTTPS in production environments
        if (app()->environment('prod', 'production', 'staging', 'dev')) {
            URL::forceScheme('https');
        }

        return $next($request);
    }
}
