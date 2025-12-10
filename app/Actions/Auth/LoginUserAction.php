<?php

namespace App\Actions\Auth;

use App\Services\CognitoService;

class LoginUserAction
{
    public function __construct(protected CognitoService $cognitoService) {}

    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array<string, mixed>
     */
    public function handle(array $credentials): array
    {
        //        try {
        return $this->cognitoService->login($credentials);
        //        } catch (Exception $e) {
        //            \Log::error($e->getMessage());
        //
        //            return ['error' => $e->getMessage()];
        //        }
    }
}
