<?php

namespace App\Integrations\HRTools\Http\Resources;

use App\Integrations\HRTools\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @group Modules/HRTools
 *
 * Ressource pour un lien individuel
 *
 * Cette ressource représente un lien externe dans le module HRTools.
 *
 * @mixin Link
 *
 * @response {
 *   "id": "123e4567-e89b-12d3-a456-426614174000",
 *   "name": "Guide RH",
 *   "description": "Guide complet pour les ressources humaines",
 *   "url": "https://example.com/guide",
 *   "logo_url": "https://example.com/logo.png",
 *   "financer_id": "123e4567-e89b-12d3-a456-426614174001",
 *   "created_at": "2024-01-01T00:00:00.000000Z",
 *   "updated_at": "2024-01-01T00:00:00.000000Z"
 * }
 */
class LinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Link $link */
        $link = $this->resource;

        return [
            'id' => $link->id,
            'name' => $link->name,
            'name_raw' => $link->getTranslations('name'),
            'description' => $link->description,
            'description_raw' => $link->getTranslations('description'),
            'url' => $link->url,
            'url_raw' => $link->getTranslations('url'),
            'logo_url' => $link->logo_url,
            'position' => $link->position,
            'financer_id' => $link->financer_id,
            'available_languages' => $link->getAvailableLanguages(),
            'created_at' => $link->created_at,
            'updated_at' => $link->updated_at,
        ];
    }
}
