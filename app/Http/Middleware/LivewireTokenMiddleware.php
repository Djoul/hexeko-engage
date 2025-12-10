<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LivewireTokenMiddleware
{
    /**
     * Handle an incoming request.
     * Automatically inject Bearer token from cookies into Livewire requests.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if this is a Livewire request
        // Try to extract token from cookie if not in Authorization header
        if ($this->isLivewireRequest($request) && ! $request->hasHeader('Authorization')) {
            $token = $this->extractTokenFromCookie($request);
            if (! in_array($token, [null, '', '0'], true)) {
                // Add Bearer token to the request header
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }

    /**
     * Determine if the request is a Livewire request.
     */
    private function isLivewireRequest(Request $request): bool
    {
        if ($request->hasHeader('X-Livewire')) {
            return true;
        }
        if (str_contains($request->path(), 'livewire/message')) {
            return true;
        }
        if (str_contains($request->path(), 'livewire/upload-file')) {
            return true;
        }

        return str_contains($request->path(), 'livewire/preview-file');
    }

    /**
     * Extract JWT token from cookie.
     */
    private function extractTokenFromCookie(Request $request): ?string
    {
        // Check multiple possible cookie names
        $cookieNames = [
            'admin_token',
            'jwt_token',
            'access_token',
            config('auth.cookie_name', 'auth_token'),
        ];

        foreach ($cookieNames as $cookieName) {
            if ($request->hasCookie($cookieName)) {
                return $request->cookie($cookieName);
            }
        }

        return null;
    }
}
