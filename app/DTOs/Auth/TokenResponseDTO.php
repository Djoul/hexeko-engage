<?php

namespace App\DTOs\Auth;

class TokenResponseDTO
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $idToken,
        public readonly ?string $refreshToken,
        public readonly int $expiresIn,
        public readonly string $tokenType,
        public readonly array $user
    ) {}

    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'id_token' => $this->idToken,
            'refresh_token' => $this->refreshToken,
            'expires_in' => $this->expiresIn,
            'token_type' => $this->tokenType,
            'user' => $this->user,
        ];
    }
}
