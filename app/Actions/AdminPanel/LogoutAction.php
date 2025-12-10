<?php

namespace App\Actions\AdminPanel;

use App\Models\User;
use App\Services\CognitoAuthService;
use Exception;
use Illuminate\Support\Facades\Log;

class LogoutAction
{
    public function __construct(
        private readonly CognitoAuthService $cognitoService
    ) {}

    public function execute(string $accessToken): array
    {
        try {
            // Get user info before logout for logging
            $user = $this->cognitoService->getUserFromToken($accessToken);

            // Revoke token with Cognito
            $result = $this->cognitoService->logout($accessToken);

            // Log successful logout
            if ($user instanceof User) {
                Log::info('Admin user logged out successfully', [
                    'email' => $user->email,
                    'user_id' => $user->id,
                ]);
            }

            return [
                'success' => true,
                'message' => 'Logged out successfully',
            ];
        } catch (Exception $e) {
            Log::error('Admin logout failed', [
                'error' => $e->getMessage(),
            ]);

            // Even if revocation fails, we consider it a successful logout
            // as the token will expire naturally
            return [
                'success' => true,
                'message' => 'Logged out successfully',
            ];
        }
    }
}
