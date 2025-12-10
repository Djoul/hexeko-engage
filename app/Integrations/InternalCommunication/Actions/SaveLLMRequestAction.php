<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Actions;

use App\Events\CreditConsumed;
use App\Integrations\InternalCommunication\DTOs\SaveLLMRequestDTO;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use App\Models\Financer;
use App\Models\LLMRequest;
use App\Projectors\CreditBalanceProjector;

class SaveLLMRequestAction
{
    public function __construct(
        private CreditBalanceProjector $creditBalanceProjector,
    ) {}

    public function execute(SaveLLMRequestDTO $dto): LLMRequest
    {
        $llmRequest = LLMRequest::create([
            'financer_id' => $dto->financerId,
            'requestable_id' => $dto->translationId,
            'requestable_type' => ArticleTranslation::class,
            'prompt' => $dto->prompt,
            'response' => $dto->response,
            'prompt_system' => $dto->promptSystem ?? config('ai.internal_communication.prompt_system'),
            'engine_used' => $dto->engineUsed,
            'tokens_used' => $dto->tokensUsed,
            'messages' => $dto->messages,
        ]);

        $this->consumeCredits($dto->financerId, $dto->tokensUsed);

        return $llmRequest;
    }

    private function consumeCredits(string $financerId, int $tokensUsed): void
    {
        if (in_array($financerId, [null, '', '0'], true)) {
            return;
        }

        $this->creditBalanceProjector->onCreditConsumed(
            new CreditConsumed(
                Financer::class,
                $financerId,
                'ai_token',
                $tokensUsed
            )
        );
    }
}
