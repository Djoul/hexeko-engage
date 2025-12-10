<?php

namespace App\DTOs\AdminPanel;

class MfaChallengeResponseDTO
{
    public function __construct(
        public readonly string $challengeName,
        public readonly string $session,
        public readonly array $challengeParameters,
        public readonly ?string $destination = null
    ) {}
}
