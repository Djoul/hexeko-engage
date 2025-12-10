<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\DTOs;

final class SaveLLMRequestDTO
{
    /**
     * @param  array<int, array<string, string>>|null  $messages
     */
    public function __construct(
        public string $translationId,
        public string $financerId,
        public string $prompt,
        public string $response,
        public int $tokensUsed,
        public ?string $promptSystem = null,
        public string $engineUsed = 'OpenAI',
        public ?array $messages = null,
    ) {}
}
