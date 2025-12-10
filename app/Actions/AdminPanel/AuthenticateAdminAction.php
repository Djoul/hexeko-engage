<?php

namespace App\Actions\AdminPanel;

use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\TokenResponseDTO;
use App\Enums\IDP\RoleDefaults;
use App\Exceptions\AuthenticationException;
use App\Models\Team;
use App\Models\User;
use App\Services\CognitoAuthService;
use Context;
use Exception;
use Illuminate\Support\Facades\Log;

class AuthenticateAdminAction
{
    public function __construct(
        private readonly CognitoAuthService $cognitoService
    ) {}

    public function execute(LoginDTO $dto): TokenResponseDTO
    {
        try {
            // Authenticate with Cognito
            $authResult = $this->cognitoService->authenticate($dto->email, $dto->password);

            if (! $authResult['success']) {
                throw new AuthenticationException($authResult['error'] ?? 'Invalid credentials');
            }

            if (! isset($authResult['access_token'])) {
                throw new AuthenticationException('Invalid authentication response');
            }

            // Get user from token via Cognito service
            $user = $this->cognitoService->getUserFromToken($authResult['access_token']);

            if (! $user instanceof User) {
                throw new AuthenticationException('User not found in application database');
            }

            // Configure Spatie Permission context
            $this->configurePermissionContext();

            // Verify GOD role with API guard
            if (! $user->hasRole(RoleDefaults::GOD, 'api')) {
                Log::warning('Non-GOD user attempted admin panel access', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames(),
                ]);
                throw new AuthenticationException('Access denied. Admin panel is restricted to GOD users only.');
            }

            // Update cognito_id if needed (will be extracted from token later if needed)

            // Log successful authentication
            Log::info('Admin user authenticated successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return new TokenResponseDTO(
                accessToken: $authResult['access_token'],
                idToken: $authResult['id_token'],
                refreshToken: $authResult['refresh_token'] ?? null,
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
            Log::error('Admin authentication failed', [
                'email' => $dto->email,
                'error' => $e->getMessage(),
            ]);
            throw new AuthenticationException('Authentication failed: '.$e->getMessage(), $e->getCode(), $e);
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
        setPermissionsTeamId(Context::get('global_team_id', Team::first(['id'])->id));
    }
}
