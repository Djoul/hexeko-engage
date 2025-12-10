<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Actions;

use App\Integrations\InternalCommunication\DTOs\UpdateArticleReactionDTO;
use App\Integrations\InternalCommunication\Models\ArticleInteraction;
use App\Integrations\InternalCommunication\Services\ArticleInteractionService;
use Illuminate\Support\Facades\DB;

class UpdateArticleReactionAction
{
    public function __construct(
        private readonly ArticleInteractionService $articleInteractionService
    ) {}

    public function execute(UpdateArticleReactionDTO $dto): ArticleInteraction
    {

        return DB::transaction(function () use ($dto): ArticleInteraction {
            $interaction = $this->articleInteractionService->findByUserAndArticle(
                $dto->userId,
                $dto->articleId
            );

            if (! $interaction instanceof ArticleInteraction && $dto->reaction === null) {
                abort(422, 'unprocessable error');
            }

            if (! $interaction instanceof ArticleInteraction && $dto->reaction !== null) {
                return $this->articleInteractionService->create([
                    'user_id' => $dto->userId,
                    'article_id' => $dto->articleId,
                    'article_translation_id' => $dto->articleTranslationId,
                    'reaction' => $dto->reaction,
                ]);
            }

            if ($interaction instanceof ArticleInteraction && $dto->reaction == null) {
                $interaction->delete();

                return new ArticleInteraction;
            }

            if ($interaction instanceof ArticleInteraction) {
                return $this->articleInteractionService->update($interaction, [
                    'reaction' => $dto->reaction,
                    'article_translation_id' => $dto->articleTranslationId,
                ]);
            }

            return new ArticleInteraction;
        });
    }
}
