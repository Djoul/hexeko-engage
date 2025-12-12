<?php

namespace App\Actions\AdminPanel;

use App\DTOs\Auth\TokenResponseDTO;
use App\Enums\IDP\RoleDefaults;
use App\Exceptions\AuthenticationException;
use App\Models\Team;
use App\Models\User;
use App\Services\CognitoAuthService;
use Context;
use Exception;
use Illuminate\Support\Facades\Log;

class RefreshTokenAction
{
    public function __construct(
        private readonly CognitoAuthService $cognitoService
    ) {}

    public function execute(string $refreshToken): TokenResponseDTO
    {
        try {
            // Refresh token with Cognito
            $authResult = $this->cognitoService->refreshToken($refreshToken);

            if (! $authResult['success']) {
                throw new AuthenticationException($authResult['error'] ?? 'Token refresh failed');
            }

            if (! isset($authResult['access_token'])) {
                throw new AuthenticationException('Invalid refresh response');
            }

            // Get user from the new token
            $user = $this->cognitoService->getUserFromToken($authResult['access_token']);

            if (! $user instanceof User) {
                throw new AuthenticationException('User not found in application database');
            }

            // Configure Spatie Permission context
            $this->configurePermissionContext();

            // Verify GOD role with API guard
            if (! $user->hasRole(RoleDefaults::GOD, 'api')) {
                Log::warning('Token refresh attempted by non-GOD user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames(),
                    'team_id' => $user->team_id ?? 'none',
                ]);
                throw new AuthenticationException('Access denied. Admin panel is restricted to GOD users only.');
            }

            // Log successful refresh
            Log::info('Admin token refreshed successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return new TokenResponseDTO(
                accessToken: $authResult['access_token'],
                idToken: $authResult['id_token'],
                refreshToken: $refreshToken, // Keep the same refresh token
                expiresIn: $authResult['expires_in'] ?? 3600,
                tokenType: 'Bearer',
                user: [
                    'id' => $user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ]
            );
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Admin token refresh failed', [
                'error' => $e->getMessage(),
            ]);
            throw new AuthenticationException('Token refresh failed: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Configure Spatie Permission context for API guard and team
     */
    private function configurePermissionContext(): void
    {
        // Set default guard to API for permission checks
        config(['permission.defaults.guard' => 'api']);
        // Set team context from user's team_id for Spatie Permission
        $globalTeamId = Context::get('global_team_id');
        if (! $globalTeamId) {
            $globalTeamId = Team::value('id') ?? Team::firstOrFail(['id'])->id;
            Context::add('global_team_id', $globalTeamId);
        }
        setPermissionsTeamId($globalTeamId);
    }
}
