<?php

namespace App\Http\Controllers\V1;

use App\Actions\Site\CreateSiteAction;
use App\Actions\Site\UpdateSiteAction;
use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\IndexSiteRequest;
use App\Http\Requests\Site\UpdateSiteRequest;
use App\Http\Resources\Site\SiteResource;
use App\Models\Site;
use App\Pipelines\FilterPipelines\SitePipeline;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class SiteController
 */
class SiteController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Site::class, 'site');
    }

    /**
     * List sites
     *
     * Return a list of sites with optional filters.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('name', description: 'Filter by name.', type: 'string', example: 'Site Name')]
    #[QueryParameter('created_at', description: 'Filter by creation date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('updated_at', description: 'Filter by update date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('deleted_at', description: 'Filter by deletion date (for soft deleted items).', type: 'date', example: '2024-01-01')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field default created_at.', type: 'string', example: 'created_at')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field default created_at.', type: 'string', example: 'created_at')]
    public function index(IndexSiteRequest $request): AnonymousResourceCollection
    {
        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        $sites = Site::query()
            ->pipe(function ($query) {
                return resolve(SitePipeline::class)->apply($query);
            })
            ->paginate($perPage);

        return SiteResource::collection($sites);
    }

    /**
     * Create site
     */
    public function store(UpdateSiteRequest $request, CreateSiteAction $createSiteAction): SiteResource
    {
        $site = $createSiteAction->execute($request->validated());

        return new SiteResource($site);
    }

    /**
     * Show site
     */
    public function show(Site $site): SiteResource
    {
        return new SiteResource($site);
    }

    /**
     * Update site
     */
    public function update(UpdateSiteRequest $request, Site $site, UpdateSiteAction $updateSiteAction): SiteResource
    {
        $site = $updateSiteAction->execute($site, $request->validated());

        return new SiteResource($site);
    }

    /**
     * Delete site
     */
    public function destroy(Site $site): Response
    {
        return response()->json(['success' => $site->delete()])->setStatusCode(204);
    }
}
