<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Actions;

use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use Illuminate\Support\Facades\DB;

class DeleteArticleAction
{
    /**
     * Handle the deletion of an article.
     */
    public function handle(Article $article): bool
    {
        return DB::transaction(function () use ($article): bool {
            $translation = $article->translation();
            $title = $translation instanceof ArticleTranslation ? $translation->title : 'Unknown';
            $result = $article->delete();

            activity('article')
                ->performedOn($article)
                ->log("Article '{$title}' deleted");

            return $result === null ? false : $result;
        });
    }
}
