<?php

namespace App\Integrations\InternalCommunication\Actions;

use App\Integrations\InternalCommunication\DTOs\CreateArticleVersionDTO;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use App\Integrations\InternalCommunication\Models\ArticleVersion;

class CreateArticleVersionAction
{
    /**
     * Crée une nouvelle version d'article.
     * Create a new article version.
     */
    public function handle(Article $article, CreateArticleVersionDTO $dto): ArticleVersion
    {
        // Get the translation if not provided in DTO
        $translationId = $dto->article_translation_id;
        $language = $dto->language;

        if (! $translationId) {
            // Try to get the translation from the article
            $translation = $article->translation();
            if ($translation instanceof ArticleTranslation) {
                $translationId = $translation->id;
                $language = $translation->language;
            } else {
                // If no translation exists, create one
                /** @var ArticleTranslation $translation */
                $translation = $article->translations()->create([
                    'language' => $language ?? app()->getLocale(),
                    'title' => 'Untitled',
                    'content' => $dto->content,
                ]);
                $translationId = $translation->id;
                $language = $translation->language;
            }
        }

        $data = [
            'content' => $dto->content,
            'title' => $dto->title,
            'version_number' => $dto->version_number,
            'prompt' => $dto->prompt,
            'llm_response' => $dto->llm_response,
            'article_id' => $article->id,
            'article_translation_id' => $translationId,
            'language' => $language,
            'author_id' => $dto->author_id,
            'llm_request_id' => $dto->llm_request_id,
            'illustration_id' => $dto->illustration_id,
        ];

        /** @var ArticleVersion $articleVersion */
        $articleVersion = ArticleVersion::create($data);

        return $articleVersion;
    }
}
