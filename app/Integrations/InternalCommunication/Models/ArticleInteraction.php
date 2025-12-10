<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Models;

use App\Integrations\InternalCommunication\Database\factories\ArticleInteractionFactory;
use App\Integrations\InternalCommunication\Models\Traits\ArticleInteractionAccessorsAndHelpers;
use App\Integrations\InternalCommunication\Models\Traits\ArticleInteractionFiltersAndScopes;
use App\Integrations\InternalCommunication\Models\Traits\ArticleInteractionRelations;
use App\Models\User;
use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * ArticleInteraction model for tracking user interactions with articles.
 *
 * @property string $id
 * @property string $user_id
 * @property string $article_id
 * @property string|null $article_translation_id
 * @property string|null $reaction
 * @property bool $is_favorite
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $emoji
 * @property-read User $user
 * @property-read Article $article
 * @property-read ArticleTranslation|null $translation
 */
class ArticleInteraction extends Model implements Auditable
{
    use ArticleInteractionAccessorsAndHelpers, AuditableModel;
    use ArticleInteractionFiltersAndScopes;
    use ArticleInteractionRelations;
    use HasFactory;
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'int_communication_rh_article_interactions';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_favorite' => 'boolean',
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory<ArticleInteraction>
     */
    protected static function newFactory(): Factory
    {
        return ArticleInteractionFactory::new();
    }
}
