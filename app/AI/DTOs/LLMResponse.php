<?php

namespace App\AI\DTOs;

class LLMResponse
{
    public readonly ?string $canvasContent;

    public readonly string $chatResponse;

    public readonly ?int $tokensUsed;

    public function __construct(string $chatResponse, ?int $tokensUsed = 0, ?string $canvasContent = null)
    {
        $this->chatResponse = $chatResponse;
        $this->tokensUsed = $tokensUsed;
        $this->canvasContent = $canvasContent;
    }
}
