<?php

namespace App\Integrations\HRTools\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Integrations\HRTools\Actions\ReorderLinksAction;
use App\Integrations\HRTools\Http\Requests\LinkReorderRequest;
use App\Integrations\HRTools\Http\Resources\LinkResourceCollection;
use App\Integrations\HRTools\Models\Link;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

/**
 * @group Modules/HRTools
 *
 * Gestion de l'ordre des liens externes pour le module HRTools
 *
 * Ce contrôleur permet de gérer l'ordre d'affichage des liens
 * dans le cadre de l'intégration HRTools.
 */
#[Group('Modules/HRTools')]
class LinkReorderController extends Controller
{
    public function __construct(
        private ReorderLinksAction $reorderLinksAction
    ) {}

    /**
     * Réordonnancement des liens
     *
     * @authenticated
     *
     * Permet de réordonner les liens en définissant leur position.
     *
     * @bodyParam links array required Liste des liens à réordonner. Example: [{"id": "123e4567-e89b-12d3-a456-426614174000", "position": 0}, {"id": "123e4567-e89b-12d3-a456-426614174001", "position": 1}]
     * @bodyParam links[].id string required ID du lien. Example: 123e4567-e89b-12d3-a456-426614174000
     * @bodyParam links[].position integer required Position du lien (0 étant la première position). Example: 0
     *
     * @response 200 {
     *   "success": true,
     *   "links": [
     *     {
     *       "id": "123e4567-e89b-12d3-a456-426614174000",
     *       "name": "Guide RH",
     *       "description": "Guide complet pour les ressources humaines",
     *       "url": "https://example.com/guide",
     *       "logo_url": "https://example.com/logo.png",
     *       "financer_id": "123e4567-e89b-12d3-a456-426614174001",
     *       "position": 0,
     *       "created_at": "2024-01-01T00:00:00.000000Z",
     *       "updated_at": "2024-01-01T12:30:45.000000Z"
     *     },
     *     {
     *       "id": "123e4567-e89b-12d3-a456-426614174001",
     *       "name": "Portail RH",
     *       "description": "Portail des ressources humaines",
     *       "url": "https://example.com/portal",
     *       "logo_url": "https://example.com/portal-logo.png",
     *       "financer_id": "123e4567-e89b-12d3-a456-426614174001",
     *       "position": 1,
     *       "created_at": "2024-01-01T00:00:00.000000Z",
     *       "updated_at": "2024-01-01T12:30:45.000000Z"
     *     }
     *   ]
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "links": [
     *       "La liste des liens est requise."
     *     ]
     *   }
     * }
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_HRTOOLS)]
    public function reorder(LinkReorderRequest $request): JsonResponse
    {
        /** @var array<int, array<string, mixed>> $links */
        $links = $request->validated('links');

        $linkIds = array_column($links, 'id');
        $existingLinks = Link::whereIn('id', $linkIds)->get();

        $existingLinks->each(function (Link $link): void {
            $this->authorize('update', $link);
        });

        $success = $this->reorderLinksAction->execute($links);

        //        $linkIds = array_column($links, 'id');

        //        $affectedLinks = Link::whereIn('id', $linkIds)->get(['id', 'financer_id']);
        //
        //        $financerIds = $affectedLinks->pluck('financer_id')->unique()->toArray();

        $query = Link::with('financer');

        // Apply pipeFiltered if available
        if (method_exists($query, 'pipeFiltered')) {
            $query = $query->pipeFiltered();
        }

        $updatedLinks = $query->get();

        return response()->json([
            'success' => $success,
            'links' => new LinkResourceCollection($updatedLinks),
        ]);
    }
}
