<?php

namespace App\Integrations\HRTools\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Integrations\HRTools\Actions\CreateLinkAction;
use App\Integrations\HRTools\Actions\DeleteLinkAction;
use App\Integrations\HRTools\Actions\UpdateLinkAction;
use App\Integrations\HRTools\Http\Requests\LinkFormRequest;
use App\Integrations\HRTools\Http\Requests\LinkIndexRequest;
use App\Integrations\HRTools\Http\Resources\LinkResource;
use App\Integrations\HRTools\Http\Resources\LinkResourceCollection;
use App\Integrations\HRTools\Models\Link;
use App\Integrations\HRTools\Services\HRToolsLinkService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Modules/HRTools
 *
 * Gestion des liens externes pour le module HRTools
 *
 * Ce contrôleur permet de gérer les liens vers des outils et ressources externes
 * dans le cadre de l'intégration HRTools.
 */
#[Group('Modules/HRTools')]
class LinkController extends Controller
{
    public function __construct(
        private HRToolsLinkService $HRToolsService,
        private CreateLinkAction $createLinkAction,
        private UpdateLinkAction $updateLinkAction,
        private DeleteLinkAction $deleteLinkAction
    ) {}

    /**
     * List of HRTools links
     *
     * This route leverages the pipeline pattern to dynamically filter results based on individual model attributes.
     *
     * @authenticated
     *
     * Retrieves a list of links with optional filters.
     *
     * @response LinkResourceCollection<LinkResource>
     */
    #[QueryParameter('name', description: 'Filter by link name.', type: 'string', example: 'Guide RH')]
    #[QueryParameter('description', description: 'Filter by link description.', type: 'string', example: 'Guide for human resources')]
    #[QueryParameter('url', description: 'Filter by link URL.', type: 'string', example: 'https://example.com/guide')]
    #[QueryParameter('logo_url', description: 'Filter by logo URL.', type: 'string', example: 'https://example.com/logo.png')]
    #[QueryParameter('api_endpoint', description: 'Filter by API endpoint.', type: 'string', example: '/api/v1/guide')]
    #[QueryParameter('front_endpoint', description: 'Filter by frontend endpoint.', type: 'string', example: '/guide')]
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('created_at', description: 'Filter by creation date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('updated_at', description: 'Filter by update date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('deleted_at', description: 'Filter by deletion date (for soft deleted items).', type: 'date', example: '2024-01-01')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field (must be in Link::$sortable) default position.', type: 'string', example: 'position')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field (must be in Link::$sortable).', type: 'string', example: 'created_at')]
    #[RequiresPermission(PermissionDefaults::READ_HRTOOLS)]
    public function index(LinkIndexRequest $request): LinkResourceCollection
    {
        $this->authorize('viewAny', Link::class);

        $perPage = $request->validated('per_page');
        $page = $request->validated('page') ?? 1;
        $perPageInt = is_numeric($perPage) ? (int) $perPage : 20;
        $pageInt = is_numeric($page) ? (int) $page : 1;

        return new LinkResourceCollection($this->HRToolsService->all($perPageInt, $pageInt));
    }

    /**
     * Creating a new link
     *
     * @authenticated
     *
     * @bodyParam name string required Nom du lien. Example: Guide RH
     * @bodyParam description string Description du lien. Example: Guide complet pour les ressources humaines
     * @bodyParam url string required URL du lien. Example: https://example.com/guide
     * @bodyParam logo_url string URL du logo (alternative à l'upload de fichier). Example: https://example.com/logo.png
     * @bodyParam api_endpoint string Endpoint API associé au lien. Example: /api/v1/guide
     * @bodyParam front_endpoint string Endpoint frontend associé au lien. Example: /guide
     * @bodyParam logo file Fichier image du logo (JPG, PNG, SVG).
     * @bodyParam financer_id uuid required ID du financeur associé au lien. Example: 123e4567-e89b-12d3-a456-426614174000
     *
     * @response 200 {
     *   "id": "123e4567-e89b-12d3-a456-426614174000",
     *   "name": "Guide RH",
     *   "description": "Guide complet pour les ressources humaines",
     *   "url": "https://example.com/guide",
     *   "logo_url": "https://example.com/logo.png",
     *   "financer_id": "123e4567-e89b-12d3-a456-426614174001",
     *   "created_at": "2024-01-01T00:00:00.000000Z",
     *   "updated_at": "2024-01-01T00:00:00.000000Z"
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "name": [
     *       "Le champ nom est obligatoire."
     *     ],
     *     "url": [
     *       "Le champ url est obligatoire."
     *     ],
     *     "financer_id": [
     *       "Le champ financer id est obligatoire."
     *     ]
     *   }
     * }
     */
    #[RequiresPermission(PermissionDefaults::CREATE_HRTOOLS)]
    public function store(LinkFormRequest $request): LinkResource
    {
        $this->authorize('create', [Link::class, $request->validated('financer_id')]);

        $link = $this->createLinkAction->execute($request->validated());

        return new LinkResource($link);
    }

    /**
     * Details of a specific link
     *
     * Retrieves detailed information on a specific link.
     *
     * @authenticated
     *
     * @urlParam link string required ID du lien à consulter. Example: 123e4567-e89b-12d3-a456-426614174000
     *
     * @response 200 {
     *   "id": "123e4567-e89b-12d3-a456-426614174000",
     *   "name": "Guide RH",
     *   "description": "Guide complet pour les ressources humaines",
     *   "url": "https://example.com/guide",
     *   "logo_url": "https://example.com/logo.png",
     *   "financer_id": "123e4567-e89b-12d3-a456-426614174001",
     *   "created_at": "2024-01-01T00:00:00.000000Z",
     *   "updated_at": "2024-01-01T00:00:00.000000Z"
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Integrations\\HRTools\\Models\\Link] <id>"
     * }
     */
    #[RequiresPermission(PermissionDefaults::READ_HRTOOLS)]
    public function show(string $id): LinkResource|Response
    {
        try {
            $link = $this->HRToolsService->find($id);
            $this->authorize('view', $link);

            return new LinkResource($link);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Article not found'], 404);
        }
    }

    /**
     * Updating an existing link
     *
     * @authenticated
     *
     * Updates existing link information.
     * Also allows logo replacement via file upload.
     *
     * @urlParam id string required ID du lien à mettre à jour. Example: 123e4567-e89b-12d3-a456-426614174000
     *
     * @bodyParam name string required Nom du lien. Example: Guide RH Mis à jour
     * @bodyParam description string Description du lien. Example: Guide complet mis à jour pour les ressources humaines
     * @bodyParam url string required URL du lien. Example: https://example.com/guide-updated
     * @bodyParam logo_url string URL du logo (alternative à l'upload de fichier). Example: https://example.com/logo-updated.png
     * @bodyParam api_endpoint string Endpoint API associé au lien. Example: /api/v1/guide-updated
     * @bodyParam front_endpoint string Endpoint frontend associé au lien. Example: /guide-updated
     * @bodyParam logo file Fichier image du logo (JPG, PNG, SVG).
     * @bodyParam financer_id uuid required ID du financeur associé au lien. Example: 123e4567-e89b-12d3-a456-426614174000
     * @bodyParam position integer Position du lien dans l'ordre d'affichage (0 étant la première position). Example: 2
     *
     * @response 200 {
     *   "id": "123e4567-e89b-12d3-a456-426614174000",
     *   "name": "Guide RH Mis à jour",
     *   "description": "Guide complet mis à jour pour les ressources humaines",
     *   "url": "https://example.com/guide-updated",
     *   "logo_url": "https://example.com/logo-updated.png",
     *   "financer_id": "123e4567-e89b-12d3-a456-426614174001",
     *   "created_at": "2024-01-01T00:00:00.000000Z",
     *   "updated_at": "2024-01-01T12:30:45.000000Z"
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Integrations\\HRTools\\Models\\Link]"
     * }
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_HRTOOLS)]
    public function update(LinkFormRequest $request, string $id): LinkResource
    {
        $link = $this->HRToolsService->find($id);
        $this->authorize('update', $link);

        if ($request->has('financer_id') && $request->validated('financer_id') !== $link->financer_id) {
            $this->authorize('create', [Link::class, $request->validated('financer_id')]);
        }

        $updatedLink = $this->updateLinkAction->execute($request->validated(), $id);

        return new LinkResource($updatedLink);
    }

    /**
     * Deleting a link
     *
     * @authenticated
     *
     * Deletes an existing link (soft delete).
     *
     * @urlParam id string required ID of the link to delete. Example: 123e4567-e89b-12d3-a456-426614174000
     *
     * @response 204 {}
     * @response 404 {
     *   "message": "No query results for model [App\\Integrations\\HRTools\\Models\\Link]"
     * }
     */
    #[RequiresPermission(PermissionDefaults::DELETE_HRTOOLS)]
    public function destroy(string $id): Response
    {
        $link = $this->HRToolsService->find($id);
        $this->authorize('delete', $link);

        return response()->json(['success' => $this->deleteLinkAction->execute($id)])->setStatusCode(204);
    }
}
