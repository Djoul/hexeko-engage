<?php

namespace App\Actions\AdminPanel;

use App\DTOs\AdminPanel\TokenResponseDTO;
use App\Enums\IDP\RoleDefaults;
use App\Exceptions\AuthenticationException;
use App\Models\Team;
use App\Models\User;
use App\Services\CognitoAuthService;
use Illuminate\Support\Facades\Log;

class VerifyMfaAction
{
    public function __construct(
        private readonly CognitoAuthService $cognitoService
    ) {}

    /**
     * Verify MFA code and complete authentication
     */
    public function execute(string $username, string $mfaCode, string $session): TokenResponseDTO
    {
        // Verify MFA code with Cognito
        $cognitoResult = $this->cognitoService->verifyMfaCode($username, $mfaCode, $session);

        if (! $cognitoResult['success']) {
            throw new AuthenticationException($cognitoResult['error']);
        }

        // Get user from access token
        $user = $this->cognitoService->getUserFromToken($cognitoResult['access_token']);

        if (! $user instanceof User) {
            Log::warning('MFA verification succeeded but user not found in database', [
                'username' => $username,
            ]);
            throw new AuthenticationException('User not found in database');
        }

        // Configure permission context for API guard and team
        $this->configurePermissionContext($user);

        // Verify user has GOD role
        if (! $user->hasRole(RoleDefaults::GOD, 'api')) {
            Log::warning('MFA verification succeeded but user lacks GOD role', [
                'user_id' => $user->id,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ]);
            throw new AuthenticationException('Access denied. Admin panel is restricted to GOD users only.');
        }

        // Load user relationships
        $user->load(['roles', 'permissions', 'financers']);

        return new TokenResponseDTO(
            accessToken: $cognitoResult['access_token'],
            idToken: $cognitoResult['id_token'],
            refreshToken: $cognitoResult['refresh_token'],
            expiresIn: $cognitoResult['expires_in'],
            tokenType: 'Bearer',
            user: $user->toArray()
        );
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
            $globalTeamId = Team::value('id');
            if ($globalTeamId) {
                setPermissionsTeamId($globalTeamId);
            }
        }
    }
}
