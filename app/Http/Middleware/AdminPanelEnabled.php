<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminPanelEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('admin-panel.enabled', false)) {
            abort(404);
        }

        return $next($request);
    }
}
