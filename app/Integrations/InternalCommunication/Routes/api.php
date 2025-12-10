<?php

use App\Integrations\InternalCommunication\Http\Controllers\ArticleChatController;
use App\Integrations\InternalCommunication\Http\Controllers\ArticleController;
use App\Integrations\InternalCommunication\Http\Controllers\ArticleInteractionController;
use App\Integrations\InternalCommunication\Http\Controllers\ArticleTranslationController;
use App\Integrations\InternalCommunication\Http\Controllers\Me\ArticleController as MeArticleController;
use App\Integrations\InternalCommunication\Http\Controllers\TagController;

Route::middleware(['api', 'auth.cognito', 'tenant.guard:financer,division', 'check.permission'])->group(function (): void {

    Route::group(['prefix' => 'api/v1/internal-communication'], function (): void {

        // region Article -->
        Route::resource('articles', ArticleController::class)
            ->except(['edit', 'create']);

        // Specific route for AI-generated articles
        Route::middleware(['check.credit'])->group(function (): void {
            Route::put('articles/{id}/chat', [ArticleChatController::class, 'generate'])->name('articles.chat_update')
                ->where('id', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

            // Specific route for AI-translated articles
            Route::put('articles/{id}/translate', [ArticleTranslationController::class, 'translate'])->name('articles.translate')
                ->where('id', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
        });

        // Specific route for AI-generated articles with selected text
        // todo uncomment when needed
        //        Route::post('articles/chat/selected-text-accepted/{article}/version/{version}', [ArticleChatController::class, 'acceptSelectedText'])
        //            ->name('articles.chat.selected-text');

        // Article interactions routes
        Route::put('articles/{id}/interactions', [ArticleInteractionController::class, 'updateReaction'])
            ->name('articles.interactions.update');

        Route::put('articles/{id}/toggle-favorite', [ArticleInteractionController::class, 'toggleFavorite'])
            ->name('articles.toggle-favorite');

        Route::get('articles/{id}/interactions', [ArticleInteractionController::class, 'getUserInteraction'])
            ->name('articles.interactions.show');

        // region Tag -->
        Route::name('articles.')->group(function (): void {
            Route::resource('tags', TagController::class)
                ->except(['edit', 'create']);
        });
    });

    // region Me - User-scoped endpoints for mobile/beneficiary -->
    Route::prefix('api/v1/me')->middleware(['check.active.financer'])->name('me.')->group(function (): void {
        Route::prefix('internal-communication')->name('internal-communication.')->group(function (): void {
            Route::get('articles', [MeArticleController::class, 'index'])->name('articles.index');
            Route::get('articles/{article}', [MeArticleController::class, 'show'])->name('articles.show');
        });
    });
});
