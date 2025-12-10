<?php

namespace App\Integrations\InternalCommunication\Traits;

use App\Integrations\InternalCommunication\Models\Article;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait TagRelations
{
    /**
     * Get the articles that belong to the tag.
     *
     * @return BelongsToMany<Article>
     */
    /** @phpstan-ignore-next-line */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(
            Article::class,
            'int_communication_rh_article_tag',
            'tag_id',
            'article_id'
        );
    }
}
