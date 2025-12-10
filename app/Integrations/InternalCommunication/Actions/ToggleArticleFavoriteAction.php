<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Actions;

use App\Integrations\InternalCommunication\DTOs\ToggleArticleFavoriteDTO;
use App\Integrations\InternalCommunication\Models\ArticleInteraction;
use App\Integrations\InternalCommunication\Services\ArticleInteractionService;
use Illuminate\Support\Facades\DB;

class ToggleArticleFavoriteAction
{
    public function __construct(
        private readonly ArticleInteractionService $articleInteractionService
    ) {}

    public function execute(ToggleArticleFavoriteDTO $dto): ArticleInteraction
    {
        return DB::transaction(function () use ($dto): ArticleInteraction {
            $interaction = $this->articleInteractionService->findByUserAndArticle(
                $dto->userId,
                $dto->articleId
            );

            if (! $interaction instanceof ArticleInteraction) {
                return $this->articleInteractionService->create([
                    'user_id' => $dto->userId,
                    'article_id' => $dto->articleId,
                    'article_translation_id' => $dto->articleTranslationId,
                    'is_favorite' => true,
                ]);
            }

            return $this->articleInteractionService->update($interaction, [
                'is_favorite' => ! $interaction->is_favorite,
            ]);
        });
    }
}
