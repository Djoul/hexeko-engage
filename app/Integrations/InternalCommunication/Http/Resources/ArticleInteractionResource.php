<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Http\Resources;

use App\Integrations\InternalCommunication\Enums\ReactionTypeEnum;
use App\Integrations\InternalCommunication\Models\ArticleInteraction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ArticleInteraction $resource
 */
class ArticleInteractionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ArticleInteraction $interaction */
        $interaction = $this->resource;

        $reaction = $interaction->getAttributeValue('reaction');

        return [
            'id' => $interaction->getKey(),
            'user_id' => $interaction->getAttributeValue('user_id'),
            'article_id' => $interaction->getAttributeValue('article_id'),
            'reaction' => $reaction,
            'reaction_emoji' => $reaction !== null
                ? ReactionTypeEnum::emoji(safeString($reaction))
                : null,
            'is_favorite' => (bool) $interaction->getAttributeValue('is_favorite'),
            'created_at' => $interaction->getAttributeValue('created_at'),
            'updated_at' => $interaction->getAttributeValue('updated_at'),
        ];
    }
}
