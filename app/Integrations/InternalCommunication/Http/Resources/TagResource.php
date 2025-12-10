<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Http\Resources;

use App\Integrations\InternalCommunication\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Tag $resource
 */
class TagResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Tag $tag */
        $tag = $this->resource;

        return [
            'id' => $tag->id,
            'financer_id' => $tag->financer_id,
            'label' => $tag->getTranslations('label'),
            'created_at' => $tag->created_at,
            'updated_at' => $tag->updated_at,
            'article_count' => $tag->getArticleCount(),
        ];
    }
}
