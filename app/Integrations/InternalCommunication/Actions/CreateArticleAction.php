<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Actions;

use App;
use App\Integrations\InternalCommunication\DTOs\CreateArticleVersionDTO;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateArticleAction
{
    public function __construct(
        protected CreateArticleVersionAction $createArticleVersionAction
    ) {}

    /**
     * Handle the creation of a new article.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Article
    {
        return DB::transaction(function () use ($data): Article {
            $illustration = $data['illustration'] ?? null;
            unset($data['illustration']);

            $translationData = [
                'language' => $data['language'] ?? App::currentLocale(),
                'title' => $data['title'] ?? '',
                'content' => $data['content'] ?? null,
            ];

            $data['author_id'] = $data['author_id'] ?? Auth()->id();

            // Remove fields that don't belong to the Article model
            unset(
                $data['language'],
                $data['title'],
                $data['content'],
                $data['tags'],
                $data['status'],
                $data['llm_response'],
                $data['prompt'],
                $data['prompt_system']
            );

            $article = Article::create($data);

            $translation = $article->translations()->create($translationData);

            if ($translation instanceof ArticleTranslation) {
                if ($illustration instanceof UploadedFile) {
                    $article->addMedia($illustration)
                        ->toMediaCollection('illustration');
                }

                $this->createAction($article, $translation);

                activity('article')
                    ->performedOn($article)
                    ->log("Article '{$translation->title}' (lang: {$translation->language}) created");
            }

            return $article;
        });
    }

    /**
     * Create the initial version for the article, linked to the translation.
     */
    protected function createAction(Article $article, ?ArticleTranslation $translation = null): void
    {
        if (! $translation instanceof ArticleTranslation) {
            $translation = $article->translations()->first();
        }
        $content = $translation->content ?? [];
        $dto = new CreateArticleVersionDTO(
            $content,
            $translation->title ?? '',
            1,
            null,
            null,
            $translation->id ?? null,
            $translation->language ?? null,
        );
        $this->createArticleVersionAction->handle($article, $dto);
    }
}
