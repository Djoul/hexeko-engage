<?php

namespace App\Integrations\InternalCommunication\Traits;

use App\Integrations\InternalCommunication\Models\ArticleInteraction;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use App\Integrations\InternalCommunication\Models\ArticleVersion;
use App\Integrations\InternalCommunication\Models\Tag;
use App\Models\LLMRequest;
use App\Models\Segment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait ArticleRelations
{
    /** @phpstan-ignore-next-line */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the segment that owns the article.
     *
     * @return BelongsTo<Segment, \App\Integrations\InternalCommunication\Models\Article>
     */
    /** @phpstan-ignore-next-line */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    /**
     * Get the versions collection for the article through translations.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough<ArticleVersion>
     */
    /** @phpstan-ignore-next-line */
    public function versions(): HasManyThrough
    {
        return $this->hasManyThrough(
            ArticleVersion::class,
            ArticleTranslation::class,
            'article_id', // Foreign key on ArticleTranslation
            'article_translation_id', // Foreign key on ArticleVersion
            'id', // Local key on Article
            'id' // Local key on ArticleTranslation
        )->orderBy('int_communication_rh_article_versions.created_at', 'desc');
    }

    /**
     * Get the interactions for the article.
     *
     * @return HasMany<ArticleInteraction>
     */
    /** @phpstan-ignore-next-line */
    public function interactions(): HasMany
    {
        return $this->hasMany(ArticleInteraction::class)->orderBy('int_communication_rh_article_interactions.created_at', 'desc');
    }

    /**
     * Get the translations for the article.
     *
     * @phpstan-return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Integrations\InternalCommunication\Models\ArticleTranslation, self>
     */
    /** @phpstan-ignore-next-line */
    public function translations(): HasMany
    {
        return $this->hasMany(ArticleTranslation::class);
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

    /**
     * Get the tags associated with the article.
     *
     * @return BelongsToMany<Tag>
     */
    /** @phpstan-ignore-next-line */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'int_communication_rh_article_tag', 'article_id', 'tag_id')
            ->withTimestamps();
    }

    public function interactionsForUser(User $user): Collection
    {
        return $this->interactions()
            ->where('int_communication_rh_article_interactions.user_id', $user->id)
            ->get();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'segment_users',
            'segment_id',
            'user_id'
        );
    }
}
