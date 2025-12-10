<?php

namespace App\Integrations\InternalCommunication\Http\Resources;

use App\Http\Resources\LLMRequest\LLMRequestResourceCollection;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ArticleTranslation */
class ArticleTranslationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = $this->resource;
        /** @var ArticleTranslation $resource */
        loadRelationIfNotLoaded($resource, ['interactions', 'llmRequests', 'versions']);

        return [
            'article_id' => $resource->article_id,
            'content' => $resource->content,
            'created_at' => $resource->created_at,
            'id' => $resource->id,
            'interactions' => $resource->interactions,
            'language' => $resource->language,
            'llm_requests' => $this->when(
                $this->relationLoaded('llmRequests'),
                function () {
                    $response = LLMRequestResourceCollection::make($this->llmRequests)->response()->getData(true);

                    return is_array($response) && array_key_exists('data', $response) ? $response['data'] : [];
                }
            ) ?? [],
            'status' => $resource->status,
            'title' => $resource->title,
            'updated_at' => $resource->updated_at,
            'versions' => $this->when(
                $this->relationLoaded('versions'),
                fn () => ArticleVersionResourceCollection::make($this->versions)
            ),
        ];
    }
}
