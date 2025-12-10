<?php

namespace App\Integrations\InternalCommunication\Models;

use App\Models\User;
use App\Traits\AuditableModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $id
 * @property string $article_id
 * @property int|string $version_number
 * @property array<string, mixed> $content
 * @property string|null $title
 * @property string|null $prompt
 * @property string|null $llm_response
 * @property string|null $llm_request_id
 * @property int|null $illustration_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ArticleVersion extends Model implements Auditable
{
    use AuditableModel;
    use HasUuids;

    // Removed automatic article loading to prevent circular N+1 queries
    protected $with = ['media'];

    protected $table = 'int_communication_rh_article_versions';

    protected $casts = [
        'id' => 'string',
        'author_id' => 'string',
        'llm_request_id' => 'string',
        //        'llm_response' => 'array',
        'content' => 'array',
    ];

    /**
     * Get the article translation that owns this version.
     * This is the primary relationship.
     *
     * @return BelongsTo<ArticleTranslation, self>
     */
    /** @phpstan-ignore-next-line */
    public function translation(): BelongsTo
    {
        return $this->belongsTo(ArticleTranslation::class, 'article_translation_id');
    }

    /**
     * Get the article that owns this version (through the translation).
     * This is a secondary relationship for backward compatibility.
     *
     * @return BelongsTo<Article, self>
     */
    /** @phpstan-ignore-next-line */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Get the author (user) that owns the article.
     *
     * @return BelongsTo<User, \App\Integrations\InternalCommunication\Models\Article>
     */
    /** @phpstan-ignore-next-line */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the media (illustration) that belongs to this version.
     *
     * @return BelongsTo<Media, self>
     */
    /** @phpstan-ignore-next-line */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'illustration_id');
    }

    /**
     * Get the author ID, falling back to the article's author ID if not set.
     *
     * @param  string|null  $value  The original value
     * @return string|null
     */
    public function getAuthorIdAttribute(?string $value)
    {
        if (! in_array($value, [null, '', '0'], true)) {
            return $value;
        }

        // Check if the article relationship is loaded to prevent lazy loading violations
        if ($this->relationLoaded('article')) {
            return $this->article->author_id ?? null;
        }

        return null;
    }
}
