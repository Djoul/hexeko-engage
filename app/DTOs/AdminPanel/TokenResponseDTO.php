<?php

namespace App\DTOs\AdminPanel;

class TokenResponseDTO
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $idToken,
        public readonly string $refreshToken,
        public readonly int $expiresIn,
        public readonly string $tokenType,
        public readonly array $user
    ) {}
}
