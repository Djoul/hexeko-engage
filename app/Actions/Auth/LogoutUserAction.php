<?php

namespace App\Actions\Auth;

use App\Services\CognitoService;
use Exception;
use Log;

class LogoutUserAction
{
    public function __construct(protected CognitoService $cognitoService) {}

    /**
     * @return array<string, string>
     */
    public function handle(string $accessToken): array
    {
        try {
            $this->cognitoService->logout($accessToken);

            return ['message' => 'Logout successful.'];
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }
}
