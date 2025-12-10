<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Models\Traits;

use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleInteraction;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin ArticleInteraction
 */
trait ArticleInteractionRelations
{
    /**
     * Get the user that owns the interaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the article that owns the interaction.
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Get the translation for this interaction (nullable).
     */
    public function translation(): BelongsTo
    {
        return $this->belongsTo(ArticleTranslation::class, 'article_translation_id');
    }
}
