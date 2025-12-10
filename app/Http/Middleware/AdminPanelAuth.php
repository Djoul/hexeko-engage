<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AdminPanel\AdminAuditLog;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminPanelAuth
{
    /**
     * Handle an incoming request for admin panel access
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (! Auth::check()) {
            $this->logAuthAttempt($request, 'unauthenticated', null);

            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            return redirect()->route('admin.auth.login');
        }

        $user = Auth::user();

        // Check for GOD role requirement
        if (config('admin-panel-navigation.security.require_god_role', true) && ! $user->hasRole('GOD')) {
            $this->logAuthAttempt($request, 'forbidden', $user->id);
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Forbidden - GOD role required'], 403);
            }
            abort(403, 'Access denied - GOD role required');
        }

        // Check IP whitelist if configured
        $ipWhitelist = config('admin-panel-navigation.security.ip_whitelist', '');
        if (! empty($ipWhitelist)) {
            $allowedIps = array_map('trim', explode(',', $ipWhitelist));

            if (! in_array($request->ip(), $allowedIps)) {
                $this->logAuthAttempt($request, 'ip_blocked', $user->id);

                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Access denied - IP not whitelisted'], 403);
                }

                abort(403, 'Access denied - IP not whitelisted');
            }
        }

        // Check for expired Cognito token if using Bearer auth
        if ($request->bearerToken()) {
            $tokenExpired = $this->checkTokenExpiry($request->bearerToken());

            if ($tokenExpired) {
                $this->logAuthAttempt($request, 'token_expired', $user->id);

                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Token expired'], 401);
                }

                return redirect()->route('login')->with('error', 'Your session has expired');
            }
        }

        // Check route-specific permissions
        $routeName = $request->route()->getName();
        $requiredPermission = $this->getRoutePermission($routeName);

        if ($requiredPermission && ! $user->can($requiredPermission)) {
            $this->logAuthAttempt($request, 'permission_denied', $user->id);

            if ($request->expectsJson()) {
                return response()->json(['error' => "Permission denied - {$requiredPermission} required"], 403);
            }

            abort(403, "Access denied - missing permission: {$requiredPermission}");
        }

        // Store session context
        $this->storeSessionContext($request);

        // Log successful access
        if (config('admin-panel-navigation.security.audit_enabled', true)) {
            $this->logSuccessfulAccess($request, $user);
        }

        // Add admin panel context to request
        $request->merge([
            'admin_panel_user' => $user,
            'admin_panel_permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ]);

        return $next($request);
    }

    /**
     * Check if Bearer token is expired
     */
    private function checkTokenExpiry(string $token): bool
    {
        try {
            // Decode JWT token (assuming it's a JWT)
            $parts = explode('.', $token);

            if (count($parts) !== 3) {
                return true; // Invalid token format
            }

            $payload = json_decode(base64_decode($parts[1]), true);

            if (! $payload || ! isset($payload['exp'])) {
                return true; // No expiry claim
            }

            // Check if token is expired
            return $payload['exp'] < time();

        } catch (Exception $e) {
            Log::error('Token validation error', ['error' => $e->getMessage()]);

            return true; // Treat as expired on error
        }
    }

    /**
     * Get required permission for a route
     */
    private function getRoutePermission(string $routeName): ?string
    {
        // Map routes to permissions
        $routePermissions = [
            'admin-panel.manager.translations' => 'admin.translations.view',
            'admin-panel.manager.translations.import' => 'admin.translations.import',
            'admin-panel.manager.translations.export' => 'admin.translations.export',
            'admin-panel.manager.roles' => 'admin.roles.view',
            'admin-panel.manager.roles.create' => 'admin.roles.manage',
            'admin-panel.manager.roles.edit' => 'admin.roles.manage',
            'admin-panel.manager.roles.delete' => 'admin.roles.manage',
            'admin-panel.manager.audit' => 'admin.audit.view',
            'admin-panel.dashboard.metrics' => 'admin.dashboard.view',
            'admin-panel.docs' => 'admin.docs.view',
        ];

        return $routePermissions[$routeName] ?? null;
    }

    /**
     * Store session context for admin panel
     */
    private function storeSessionContext(Request $request): void
    {
        $path = $request->path();
        $segments = explode('/', $path);

        // Extract pillar and section from path
        $pillar = null;
        $section = null;

        if (count($segments) >= 2 && $segments[0] === 'admin-panel') {
            $pillar = $segments[1] ?? null;
            $section = $segments[2] ?? null;
        }

        // Store in session
        session([
            'admin_panel.current_pillar' => $pillar,
            'admin_panel.current_section' => $section,
            'admin_panel.last_activity' => now(),
        ]);
    }

    /**
     * Log authentication attempt
     */
    private function logAuthAttempt(Request $request, string $result, ?string $userId): void
    {
        if (! config('admin-panel-navigation.security.audit_enabled', true)) {
            return;
        }

        try {
            AdminAuditLog::create([
                'user_id' => $userId,
                'action' => 'auth_attempt',
                'entity_type' => 'authentication',
                'entity_id' => $result,
                'old_values' => null,
                'new_values' => [
                    'result' => $result,
                    'path' => $request->path(),
                    'method' => $request->method(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now(),
            ]);
        } catch (Exception $e) {
            // Don't break the request if logging fails
            Log::error('Failed to log auth attempt', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Log successful access
     */
    private function logSuccessfulAccess(Request $request, $user): void
    {
        try {
            // Only log navigation changes, not every request
            $lastPath = session('admin_panel.last_path');
            $currentPath = $request->path();

            if ($lastPath !== $currentPath) {
                AdminAuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'navigate',
                    'entity_type' => 'navigation',
                    'entity_id' => $currentPath,
                    'old_values' => ['path' => $lastPath],
                    'new_values' => ['path' => $currentPath],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'timestamp' => now(),
                ]);

                session(['admin_panel.last_path' => $currentPath]);
            }
        } catch (Exception $e) {
            // Don't break the request if logging fails
            Log::error('Failed to log navigation', ['error' => $e->getMessage()]);
        }
    }
}
