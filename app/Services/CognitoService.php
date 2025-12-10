<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\AuthRepositoryInterface;

class CognitoService
{
    public function __construct(protected AuthRepositoryInterface $repository) {}

    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array<string, mixed>
     */
    public function login(array $credentials): array
    {
        return $this->repository->login($credentials);
    }

    public function logout(string $accessToken): bool
    {
        return $this->repository->logout($accessToken);
    }

    /**
     * @return array<string, mixed>
     */
    public function createUser(User $user): array
    {
        return $this->repository->register($user);
    }

    /**
     * Réinitialise le mot de passe d'un utilisateur dans Cognito
     *
     * @param  User  $user  L'utilisateur dont le mot de passe doit être réinitialisé
     * @param  string  $tempPassword  Le nouveau mot de passe temporaire
     * @return array<string, mixed>
     */
    public function resetPassword(User $user, string $tempPassword): array
    {
        return $this->repository->resetPassword($user, $tempPassword);
    }
}
