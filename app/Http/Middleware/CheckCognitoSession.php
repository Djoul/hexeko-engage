<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\CognitoAuthService;
use Closure;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckCognitoSession
{
    public function __construct(
        private CognitoAuthService $cognitoService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Skip check for login routes
        if ($request->routeIs(['login', 'login.post', 'auth.callback'])) {
            return $next($request);
        }

        // Check if user is authenticated
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        // Check if Cognito token is expired
        $expiresAt = session('cognito_expires_at');
        if ($expiresAt && (is_string($expiresAt) || $expiresAt instanceof DateTimeInterface) && now()->isAfter($expiresAt)) {
            // Try to refresh the token
            $refreshToken = session('cognito_refresh_token');
            if (is_string($refreshToken)) {
                $result = $this->cognitoService->refreshToken($refreshToken);
                if ($result['success']) {
                    // Update session with new tokens
                    $expiresIn = is_numeric($result['expires_in']) ? (int) $result['expires_in'] : 3600;
                    session([
                        'cognito_access_token' => $result['access_token'],
                        'cognito_id_token' => $result['id_token'],
                        'cognito_expires_at' => now()->addSeconds($expiresIn),
                    ]);
                } else {
                    // Refresh failed, logout user
                    Auth::logout();
                    session()->flush();

                    return redirect()->route('login')
                        ->with('error', 'Your session has expired. Please login again.');
                }
            }
        }

        return $next($request);
    }
}
