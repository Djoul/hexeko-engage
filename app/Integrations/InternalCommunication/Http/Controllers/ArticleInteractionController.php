<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Integrations\InternalCommunication\Actions\ToggleArticleFavoriteAction;
use App\Integrations\InternalCommunication\Actions\UpdateArticleReactionAction;
use App\Integrations\InternalCommunication\DTOs\ToggleArticleFavoriteDTO;
use App\Integrations\InternalCommunication\DTOs\UpdateArticleReactionDTO;
use App\Integrations\InternalCommunication\Http\Requests\ArticleInteractionRequest;
use App\Integrations\InternalCommunication\Http\Resources\ArticleInteractionResource;
use App\Integrations\InternalCommunication\Models\ArticleInteraction;
use App\Integrations\InternalCommunication\Services\ArticleService;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

#[Group('Modules/internal-communication')]
class ArticleInteractionController extends Controller
{
    public function __construct(
        protected ArticleService $service,
        protected UpdateArticleReactionAction $updateArticleReactionAction,
        protected ToggleArticleFavoriteAction $toggleArticleFavoriteAction,
    ) {}

    /**
     * Update or create a reaction for an article.
     */
    public function updateReaction(ArticleInteractionRequest $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        try {
            $article = $this->service->find($id);
        } catch (ModelNotFoundException) {
            return response()->json(['error' => 'Article not found'], 404);
        }

        $reaction = $request->validated('reaction');
        $articleTranslationId = $request->validated('article_translation_id');

        $dto = new UpdateArticleReactionDTO(
            userId: (string) $user->id,
            articleId: $article->id,
            reaction: is_string($reaction) ? $reaction : null,
            articleTranslationId: is_string($articleTranslationId) ? $articleTranslationId : null,
        );

        $interaction = $this->updateArticleReactionAction->execute($dto);

        return response()->json([
            'data' => new ArticleInteractionResource($interaction),
            'message' => is_null($dto->reaction) ? 'Reaction removed successfully' : 'Reaction updated successfully',
        ]);
    }

    /**
     * Toggle favorite status for an article.
     */
    public function toggleFavorite(string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        try {
            $article = $this->service->find($id);
        } catch (ModelNotFoundException) {
            return response()->json(['error' => 'Article not found'], 404);
        }

        $dto = new ToggleArticleFavoriteDTO(
            userId: (string) $user->id,
            articleId: $article->id,
        );

        $interaction = $this->toggleArticleFavoriteAction->execute($dto);

        return response()->json([
            'data' => new ArticleInteractionResource($interaction),
            'message' => $interaction->is_favorite ? 'Article marked as favorite' : 'Article removed from favorites',
        ]);
    }

    /**
     * Get the current user's interaction with an article.
     */
    public function getUserInteraction(string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        try {
            $article = $this->service->find($id);
        } catch (ModelNotFoundException) {
            return response()->json(['error' => 'Article not found'], 404);
        }

        $interaction = ArticleInteraction::where([
            'user_id' => $user->id,
            'article_id' => $article->id,
        ])->first();

        if (! $interaction) {
            return response()->json([
                'data' => null,
                'message' => 'No interaction found',
            ]);
        }

        return response()->json([
            'data' => new ArticleInteractionResource($interaction),
        ]);
    }
}
