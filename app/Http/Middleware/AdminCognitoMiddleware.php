<?php

namespace App\Http\Middleware;

use App\Enums\IDP\RoleDefaults;
use App\Models\Team;
use App\Models\User;
use App\Services\CognitoAuthService;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminCognitoMiddleware
{
    public function __construct(
        private readonly CognitoAuthService $cognitoService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Extract token from header or cookie
            $token = $this->extractToken($request);

            if (in_array($token, [null, '', '0'], true)) {
                Log::warning('Admin panel access attempted without token', [
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                ]);

                return $this->handleUnauthorized($request, 'Authorization token is missing');
            }

            // Validate token with Cognito
            if (! $this->cognitoService->validateAccessToken($token)) {
                Log::warning('Admin panel access attempted with invalid token', [
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                ]);

                return $this->handleUnauthorized($request, 'Token expired or invalid');
            }

            // Get user from token
            $user = $this->cognitoService->getUserFromToken($token);

            if (! $user instanceof User) {
                Log::warning('Admin panel access attempted by unknown user', [
                    'ip' => $request->ip(),
                ]);

                return $this->handleUnauthorized($request, 'User not found');
            }

            // Configure Spatie Permission context for API guard and team
            $this->configurePermissionContext($user);

            // Verify GOD role with API guard
            if (! $user->hasRole(RoleDefaults::GOD, 'api')) {
                Log::warning('Admin panel access denied - insufficient privileges', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames(),
                    'team_id' => $user->team_id ?? 'none',
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                ]);

                return $this->handleForbidden($request, 'Access denied. Admin panel is restricted to GOD users only.');
            }

            // Set authenticated user
            auth()->setUser($user);
            $request->attributes->set('auth_user', $user);
            $request->attributes->set('bearer_token', $token);

            return $next($request);
        } catch (Exception $e) {
            Log::error('Admin middleware error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'path' => $request->path(),
            ]);

            return $this->handleError($request, 'Authentication failed');
        }
    }

    private function handleUnauthorized(Request $request, string $message): Response
    {
        // For API requests or AJAX requests, return JSON
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => $message], 401);
        }

        // For web requests, redirect to login page
        session(['intended' => $request->fullUrl()]);

        return redirect()->route('admin.auth.login')
            ->with('error', $message);
    }

    private function handleForbidden(Request $request, string $message): Response
    {
        // For API requests or AJAX requests, return JSON
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => $message], 403);
        }

        // For web requests, redirect to login page
        session(['intended' => $request->fullUrl()]);

        return redirect()->route('admin.auth.login')
            ->with('error', $message);
    }

    private function handleError(Request $request, string $message): Response
    {
        // For API requests or AJAX requests, return JSON
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => $message], 500);
        }

        // For web requests, redirect to login page with error
        return redirect()->route('admin.auth.login')
            ->with('error', 'An error occurred. Please try again.');
    }

    private function extractToken(Request $request): ?string
    {
        // Priority 1: Authorization header
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Priority 2: admin_token cookie (for Livewire support)
        $cookieToken = $request->cookie('admin_token');
        if ($cookieToken) {
            return $cookieToken;
        }

        // Priority 3: bearer_token in request (for internal redirects)
        if ($request->has('bearer_token')) {
            return $request->input('bearer_token');
        }

        return null;
    }

    /**
     * Configure Spatie Permission context for API guard and team
     */
    private function configurePermissionContext(User $user): void
    {
        // Set default guard to API for permission checks
        config(['permission.defaults.guard' => 'api']);

        // Set team context from user's team_id for Spatie Permission
        if ($user->team_id) {
            setPermissionsTeamId($user->team_id);
        } else {
            // Fallback to first available team if user has no team assigned
            $firstTeam = Team::first(['id']);
            if ($firstTeam) {
                setPermissionsTeamId($firstTeam->id);
            }
        }
    }
}
