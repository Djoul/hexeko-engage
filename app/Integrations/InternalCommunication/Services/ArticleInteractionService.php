<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Services;

use App\Integrations\InternalCommunication\Models\ArticleInteraction;

class ArticleInteractionService
{
    /**
     * Find interaction by user and article.
     */
    public function findByUserAndArticle(string $userId, string $articleId): ?ArticleInteraction
    {
        return ArticleInteraction::where('user_id', $userId)
            ->where('article_id', $articleId)
            ->first();
    }

    /**
     * Create a new article interaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ArticleInteraction
    {
        return ArticleInteraction::create($data);
    }

    /**
     * Update an existing article interaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ArticleInteraction $interaction, array $data): ArticleInteraction
    {
        $interaction->update($data);

        return $interaction;
    }
}
