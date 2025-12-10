<?php

namespace App\Integrations\InternalCommunication\Models;

use App\Models\LLMRequest;
use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $title
 * @property string $content
 * @property array<string>|null $tags
 * @property string $language
 * @property string $status
 * @property string $id
 * @property string $article_id
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class ArticleTranslation extends Model implements Auditable
{
    use AuditableModel;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'int_communication_rh_article_translations';

    protected $casts = [
        'tags' => 'array',
        'content' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * Get the article that owns this translation.
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Get the versions for this translation.
     *
     * @return HasMany<ArticleVersion>
     */
    /** @phpstan-ignore-next-line */
    public function versions(): HasMany
    {
        return $this->hasMany(ArticleVersion::class, 'article_translation_id', 'id');
    }

    /**
     * Get the interactions for the article.
     *
     * @return HasMany<ArticleInteraction, $this>
     *
     * @deprecated This relation should not be used. Reactions are linked at the article level, not translation level.
     *             Use $article->interactions instead to get all reactions for an article across all translations.
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(ArticleInteraction::class, 'article_translation_id', 'id');
    }

    /**
     * Get the LLM requests for this article.
     *
     * @return MorphMany<LLMRequest>
     */
    /** @phpstan-ignore-next-line */
    public function llmRequests(): MorphMany
    {
        return $this->morphMany(LLMRequest::class, 'llmRequestable', 'requestable_type', 'requestable_id')->orderBy('llm_requests.created_at', 'desc');
    }
}
