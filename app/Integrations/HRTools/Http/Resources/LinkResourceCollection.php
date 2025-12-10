<?php

namespace App\Integrations\HRTools\Http\Resources;

use App\Models\Financer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * @group Modules/HRTools
 *
 * Collection de liens HRTools
 *
 * Cette ressource représente une collection paginée de liens externes dans le module HRTools.
 * Elle inclut également des métadonnées comme la liste des financeurs disponibles.
 *
 * @see \App\Integrations\HRTools\Models\Link
 *
 * @response {
 *   "data": [
 *     {
 *       "id": "123e4567-e89b-12d3-a456-426614174000",
 *       "name": "Guide RH",
 *       "description": "Guide complet pour les ressources humaines",
 *       "url": "https://example.com/guide",
 *       "logo_url": "https://example.com/logo.png",
 *       "financer_id": "123e4567-e89b-12d3-a456-426614174001",
 *       "created_at": "2024-01-01T00:00:00.000000Z",
 *       "updated_at": "2024-01-01T00:00:00.000000Z"
 *     },
 *     {
 *       "id": "223e4567-e89b-12d3-a456-426614174000",
 *       "name": "Portail Collaborateur",
 *       "description": "Accès au portail collaborateur",
 *       "url": "https://example.com/portail",
 *       "logo_url": "https://example.com/portail-logo.png",
 *       "financer_id": "123e4567-e89b-12d3-a456-426614174001",
 *       "created_at": "2024-01-01T00:00:00.000000Z",
 *       "updated_at": "2024-01-01T00:00:00.000000Z"
 *     }
 *   ],
 *   "meta": {
 *     "total": 2,
 *     "financers": [
 *       {
 *         "value": "123e4567-e89b-12d3-a456-426614174001",
 *         "label": "Acme Corporation"
 *       },
 *       {
 *         "value": "223e4567-e89b-12d3-a456-426614174001",
 *         "label": "Globex Industries"
 *       }
 *     ]
 *   }
 * }
 */
class LinkResourceCollection extends ResourceCollection
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->collection->count(),
                'financers' => Financer::get()->map(fn ($financer): array => [
                    'value' => $financer->id,
                    'label' => $financer->name,
                ]),
            ],
        ];
    }
}
