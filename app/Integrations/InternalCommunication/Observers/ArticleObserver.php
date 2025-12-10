<?php

namespace App\Integrations\InternalCommunication\Observers;

use App\Integrations\InternalCommunication\Models\Article;

class ArticleObserver
{
    /**
     * Handle the Article "created" event.
     */
    public function created(Article $article): void
    {
        //
    }

    /**
     * Handle the Article "updated" event.
     */
    public function updated(Article $article): void
    {
        //
    }

    /**
     * Handle the Article "deleting" event.
     */
    public function deleting(Article $article): void
    {
        // Delete all related LLM requests
        $article->llmRequests()->delete();
    }

    /**
     * Handle the Article "deleted" event.
     */
    public function deleted(Article $article): void
    {
        //
    }

    /**
     * Handle the Article "restored" event.
     */
    public function restored(Article $article): void
    {
        //
    }

    /**
     * Handle the Article "force deleting" event.
     */
    public function forceDeleting(Article $article): void
    {
        // Delete all related LLM requests on force delete
        $article->llmRequests()->delete();
    }

    /**
     * Handle the Article "force deleted" event.
     */
    public function forceDeleted(Article $article): void
    {
        //
    }
}
