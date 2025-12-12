<?php

namespace App\Http\Controllers\AdminPanel;

use App\Actions\AdminPanel\AuthenticateAdminAction;
use App\Actions\AdminPanel\LogoutAction;
use App\Actions\AdminPanel\RefreshTokenAction;
use App\Actions\AdminPanel\ValidateTokenAction;
use App\Actions\AdminPanel\VerifyMfaAction;
use App\DTOs\Auth\LoginDTO;
use App\Exceptions\AuthenticationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminPanel\LoginRequest;
use App\Http\Requests\AdminPanel\RefreshTokenRequest;
use App\Http\Requests\AdminPanel\VerifyMfaRequest;
use App\Models\Team;
use App\Services\CognitoAuthService;
use Context;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AdminPanelAuthController extends Controller
{
    public function __construct(
        private readonly AuthenticateAdminAction $authenticateAction,
        private readonly RefreshTokenAction $refreshTokenAction,
        private readonly ValidateTokenAction $validateTokenAction,
        private readonly LogoutAction $logoutAction,
        private readonly VerifyMfaAction $verifyMfaAction
    ) {}

    /**
     * Show login page
     */
    public function showLogin(): View
    {
        return view('admin-panel.auth.login');
    }

    /**
     * Authenticate admin user and return JWT tokens or MFA challenge
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $globalTeamId = Team::value('id') ?? Team::firstOrFail(['id'])->id;
        Context::add('global_team_id', $globalTeamId);
        try {
            // First, try direct authentication with Cognito
            $cognitoResult = app(CognitoAuthService::class)->authenticate(
                $request->input('email'),
                $request->input('password')
            );

            if (! $cognitoResult['success']) {
                throw new AuthenticationException($cognitoResult['error'] ?? 'Invalid credentials');
            }

            // Check if MFA is required
            if (isset($cognitoResult['requires_mfa']) && $cognitoResult['requires_mfa']) {
                return response()->json([
                    'requires_mfa' => true,
                    'challenge_name' => $cognitoResult['challenge_name'],
                    'session' => $cognitoResult['session'],
                    'destination' => $cognitoResult['destination'],
                    'username' => $request->input('email'), // Keep for MFA verification
                ], 200);
            }

            // If no MFA required, proceed with normal authentication
            $dto = new LoginDTO(
                email: $request->input('email'),
                password: $request->input('password')
            );

            $response = $this->authenticateAction->execute($dto);

            // Set secure httpOnly cookie for Livewire support
            $cookie = Cookie::make(
                'admin_token',
                $response->accessToken,
                $response->expiresIn / 60, // Convert seconds to minutes
                '/',
                config('session.domain'),
                config('session.secure', true),
                true, // httpOnly
                false, // raw
                config('session.same_site', 'strict')
            );

            return response()->json([
                'access_token' => $response->accessToken,
                'id_token' => $response->idToken,
                'refresh_token' => $response->refreshToken,
                'expires_in' => $response->expiresIn,
                'token_type' => $response->tokenType,
                'user' => $response->user,
            ])->withCookie($cookie);
        } catch (AuthenticationException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 401);
        } catch (Exception $e) {
            Log::error('Admin login failed', [
                'error' => $e->getMessage(),
                'email' => $request->input('email'),
            ]);

            return response()->json([
                'error' => 'Authentication failed',
            ], 500);
        }
    }

    /**
     * Verify MFA code and complete authentication
     */
    public function verifyMfa(VerifyMfaRequest $request): JsonResponse
    {
        $globalTeamId = Team::value('id') ?? Team::firstOrFail(['id'])->id;
        Context::add('global_team_id', $globalTeamId);
        try {
            $response = $this->verifyMfaAction->execute(
                username: $request->input('username'),
                mfaCode: $request->input('mfa_code'),
                session: $request->input('session')
            );

            // Set secure httpOnly cookie for Livewire support
            $cookie = Cookie::make(
                'admin_token',
                $response->accessToken,
                $response->expiresIn / 60, // Convert seconds to minutes
                '/',
                config('session.domain'),
                config('session.secure', true),
                true, // httpOnly
                false, // raw
                config('session.same_site', 'strict')
            );

            return response()->json([
                'access_token' => $response->accessToken,
                'id_token' => $response->idToken,
                'refresh_token' => $response->refreshToken,
                'expires_in' => $response->expiresIn,
                'token_type' => $response->tokenType,
                'user' => $response->user,
            ])->withCookie($cookie);
        } catch (AuthenticationException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 401);
        } catch (Exception $e) {
            Log::error('MFA verification failed', [
                'error' => $e->getMessage(),
                'username' => $request->input('username'),
            ]);

            return response()->json([
                'error' => 'MFA verification failed',
            ], 500);
        }
    }

    /**
     * Refresh access token using refresh token
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        try {
            $refreshToken = $request->input('refresh_token');
            $response = $this->refreshTokenAction->execute($refreshToken);

            // Update secure cookie with new access token
            $cookie = Cookie::make(
                'admin_token',
                $response->accessToken,
                $response->expiresIn / 60,
                '/',
                config('session.domain'),
                config('session.secure', true),
                true,
                false,
                config('session.same_site', 'strict')
            );

            return response()->json([
                'access_token' => $response->accessToken,
                'id_token' => $response->idToken,
                'refresh_token' => $response->refreshToken,
                'expires_in' => $response->expiresIn,
                'token_type' => $response->tokenType,
                'user' => $response->user,
            ])->withCookie($cookie);
        } catch (AuthenticationException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 401);
        } catch (Exception $e) {
            Log::error('Token refresh failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Token refresh failed',
            ], 500);
        }
    }

    /**
     * Validate current access token
     */
    public function validateToken(Request $request): JsonResponse
    {
        try {
            // Get token from Authorization header or cookie
            $token = $this->extractToken($request);

            if (in_array($token, [null, '', '0'], true)) {
                return response()->json([
                    'valid' => false,
                    'error' => 'No token provided',
                ], 401);
            }

            $result = $this->validateTokenAction->execute($token);

            if (! $result['valid']) {
                return response()->json($result, 401);
            }

            return response()->json($result);
        } catch (Exception $e) {
            Log::error('Token validation failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'valid' => false,
                'error' => 'Validation failed',
            ], 500);
        }
    }

    /**
     * Logout admin user
     */
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        try {
            // Get token from Authorization header or cookie
            $token = $this->extractToken($request);

            if (! in_array($token, [null, '', '0'], true)) {
                $this->logoutAction->execute($token);
            }

            // Clear admin token cookie
            $cookie = Cookie::forget('admin_token');

            // For API requests or AJAX requests, return JSON
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Logged out successfully',
                ])->withCookie($cookie);
            }

            // For web requests, redirect to login page
            return redirect()->route('admin.auth.login')
                ->with('success', 'You have been logged out successfully.')
                ->withCookie($cookie);
        } catch (Exception $e) {
            Log::error('Logout failed', [
                'error' => $e->getMessage(),
            ]);

            // Still clear the cookie even if Cognito logout fails
            $cookie = Cookie::forget('admin_token');

            // For API requests or AJAX requests, return JSON
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Logged out successfully',
                ])->withCookie($cookie);
            }

            // For web requests, redirect to login page
            return redirect()->route('admin.auth.login')
                ->with('success', 'You have been logged out successfully.')
                ->withCookie($cookie);
        }
    }

    /**
     * Test authentication endpoint
     */
    public function testAuth(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        return response()->json([
            'message' => 'Authenticated successfully',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }

    /**
     * Redirect to admin login page
     */
    public function redirectToLogin(Request $request): RedirectResponse
    {
        // Store the intended URL for post-login redirect
        if ($request->fullUrl() !== route('admin.auth.login')) {
            session(['intended' => $request->fullUrl()]);
        }

        return redirect()->route('admin.auth.login')
            ->with('message', 'Please log in to access the admin panel.');
    }

    /**
     * Extract token from request (header or cookie)
     */
    private function extractToken(Request $request): ?string
    {
        // Priority 1: Authorization header
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Priority 2: admin_token cookie
        return $request->cookie('admin_token');
    }
}
