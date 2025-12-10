<?php

namespace App\AI;

use App\AI\Contracts\LLMClientInterface;
use InvalidArgumentException;

class LLMRouterService
{
    /**
     * @var array<string, LLMClientInterface>
     */
    protected array $clients;

    /**
     * @param  LLMClientInterface[]  $clients
     */
    public function __construct(iterable $clients)
    {
        foreach ($clients as $client) {
            $this->clients[$client->getName()] = $client;
        }
    }

    public function select(?string $engine = null): LLMClientInterface
    {
        $engineName = $engine ?? config('ai.default_engine');
        $engineKey = is_string($engineName) ? $engineName : 'OpenAI'; // Default to openai if not a string

        if (! array_key_exists($engineKey, $this->clients)) {
            throw new InvalidArgumentException("LLM engine [{$engineKey}] is not supported.");
        }

        return $this->clients[$engineKey];
    }
}
