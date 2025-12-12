<?php

namespace App\Actions\AdminPanel;

use App\Enums\IDP\RoleDefaults;
use App\Models\Team;
use App\Models\User;
use App\Services\CognitoAuthService;
use Carbon\Carbon;
use Context;
use Exception;
use Illuminate\Support\Facades\Log;

class ValidateTokenAction
{
    public function __construct(
        private readonly CognitoAuthService $cognitoService
    ) {}

    public function execute(string $bearerToken): array
    {
        try {
            // Validate token with Cognito
            $isValid = $this->cognitoService->validateAccessToken($bearerToken);

            if (! $isValid) {
                return [
                    'valid' => false,
                    'error' => 'Token expired',
                ];
            }

            // Get user from token
            $user = $this->cognitoService->getUserFromToken($bearerToken);

            if (! $user instanceof User) {
                return [
                    'valid' => false,
                    'error' => 'User not found in application database',
                ];
            }

            // Configure Spatie Permission context
            $this->configurePermissionContext();

            // Verify GOD role with API guard
            if (! $user->hasRole(RoleDefaults::GOD, 'api')) {
                Log::warning('Token validation attempted by non-GOD user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);

                return [
                    'valid' => false,
                    'error' => 'Access denied. Admin panel is restricted to GOD users only.',
                ];
            }

            // Get token expiry
            $expiryTime = $this->cognitoService->getTokenExpiry($bearerToken);
            $now = now();
            $expiresInMinutes = $expiryTime instanceof Carbon ? $now->diffInMinutes($expiryTime, false) : 0;

            return [
                'valid' => true,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'roles' => $user->getRoleNames()->toArray(),
                ],
                'expires_at' => $expiryTime instanceof Carbon ? $expiryTime->toISOString() : null,
                'expires_in_minutes' => max(0, $expiresInMinutes),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            ];
        } catch (Exception $e) {
            Log::error('Token validation failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'error' => $e->getMessage(),
            ];
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
