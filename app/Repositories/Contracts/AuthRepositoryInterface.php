<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Exception;

interface AuthRepositoryInterface
{
    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public function register(User $user): array;

    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function login(array $credentials): array;

    /**
     * Logout a user.
     */
    public function logout(string $accessToken): bool;

    /**
     * Réinitialise le mot de passe d'un utilisateur
     *
     * @param  User  $user  L'utilisateur dont le mot de passe doit être réinitialisé
     * @param  string  $tempPassword  Le nouveau mot de passe temporaire
     * @return array<string, mixed>
     */
    public function resetPassword(User $user, string $tempPassword): array;
}
