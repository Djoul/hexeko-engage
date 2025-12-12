<?php

namespace App\Actions\Auth;

use App\Services\CognitoService;
use Exception;
use Illuminate\Support\Facades\Log;

class LoginUserAction
{
    public function __construct(protected CognitoService $cognitoService) {}

    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array<string, mixed>
     */
    public function handle(array $credentials): array
    {
        Log::info('LoginUserAction');
        try {
            return $this->cognitoService->login($credentials);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }
}
