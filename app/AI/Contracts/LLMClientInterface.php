<?php

namespace App\AI\Contracts;

use App\AI\DTOs\LLMResponse;

interface LLMClientInterface
{
    /**
     * Send a prompt to the LLM and return a structured response.
     *
     * @param  array<string,mixed>  $prompt  The prompt to send to the LLM
     * @param  array<string,mixed>  $params  Additional parameters for the request
     * @return LLMResponse The structured response
     */
    public function sendPrompt(array $prompt, array $params = []): LLMResponse;

    /**
     * Return the name of the LLM engine used (e.g.  openai).
     *
     * @return string The name of the engine
     */
    public function getName(): string;
}
