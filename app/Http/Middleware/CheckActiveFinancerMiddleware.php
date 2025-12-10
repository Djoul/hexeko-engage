<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckActiveFinancerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Check if user has at least one active financer
        $hasActiveFinancer = $user->financers()
            ->wherePivot('active', true)
            ->exists();

        if (! $hasActiveFinancer) {
            return response()->json([
                'message' => 'User must have at least one active financer',
            ], 403);
        }

        return $next($request);
    }
}
