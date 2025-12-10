<?php

namespace App\Integrations\HRTools\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @group Modules/HRTools
 *
 * Validation des paramètres de filtrage pour les requêtes de liste et de détail des liens
 *
 * @queryParam name string Filter par nom du lien. Example: Guide RH
 * @queryParam description string Filter par description du lien. Example: Guide pour les ressources humaines
 * @queryParam url string Filter par URL du lien. Example: https://example.com/guide
 * @queryParam logo_url string Filter par URL du logo. Example: https://example.com/logo.png
 * @queryParam api_endpoint string Filter par endpoint API. Example: /api/v1/guide
 * @queryParam front_endpoint string Filter par endpoint frontend. Example: /guide
 * @queryParam financer_id uuid Filter par ID du financeur. Example: 123e4567-e89b-12d3-a456-426614174000
 * @queryParam created_at date Filter par date de création. Example: 2024-01-01
 * @queryParam updated_at date Filter par date de mise à jour. Example: 2024-01-01
 * @queryParam deleted_at date Filter par date de suppression (pour les éléments en soft delete). Example: 2024-01-01
 * @queryParam per_page integer Nombre d'éléments par page (1-100). Example: 20
 */
class LinkIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string',
            'description' => 'sometimes|string',
            'url' => 'sometimes|string',
            'logo_url' => 'sometimes|string',
            'api_endpoint' => 'sometimes|string',
            'front_endpoint' => 'sometimes|string',
            'financer_id' => 'sometimes|string|uuid',
            'created_at' => 'sometimes|date',
            'updated_at' => 'sometimes|date',
            'deleted_at' => 'sometimes|date',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ];
    }
}
