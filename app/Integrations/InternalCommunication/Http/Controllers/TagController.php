<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Integrations\InternalCommunication\Actions\CreateTagAction;
use App\Integrations\InternalCommunication\Actions\DeleteTagAction;
use App\Integrations\InternalCommunication\Actions\UpdateTagAction;
use App\Integrations\InternalCommunication\Http\Requests\TagFormRequest;
use App\Integrations\InternalCommunication\Http\Resources\TagResource;
use App\Integrations\InternalCommunication\Models\Tag;
use App\Integrations\InternalCommunication\Services\TagService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tag Controller
 *
 * Note: financer_id filtering is handled automatically by HasFinancer global scope.
 */
#[Group('Modules/internal-communication/Tags')]
class TagController extends Controller
{
    /**
     * TagController constructor.
     */
    public function __construct(
        protected TagService $tagService,
        protected CreateTagAction $createTagAction,
        protected UpdateTagAction $updateTagAction,
        protected DeleteTagAction $deleteTagAction,
    ) {
        // Authorization is handled by:
        // 1. #[RequiresPermission] attributes on each method
        // Note: authorizeResource() removed because it conflicts with HasFinancerScope
        // during route model binding before Context is set from query parameters
    }

    /**
     * List tags.
     *
     * This route leverages the pipeline pattern to dynamically filter results based on individual model attributes.
     * Filtering is centralized in TagPipeline:
     * - financer_id: Auto-filtered by HasFinancer global scope
     */
    #[RequiresPermission(PermissionDefaults::READ_TAG)]
    #[QueryParameter('search', description: 'Search global on differents fields (label)', type: 'string', example: 'framework')]
    #[QueryParameter('id', description: 'UUID of the tag.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('label', description: 'Label of the tag (partial search).', type: 'string', example: 'Framework')]
    #[QueryParameter('per_page', description: 'Number of items per page.', type: 'integer', example: '15')]
    #[QueryParameter('page', description: 'Page number.', type: 'integer', example: '1')]
    #[QueryParameter('order-by', description: 'Ascending sort field (must be in Tag::$sortable).', type: 'string', example: 'label')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field (must be in Tag::$sortable).', type: 'string', example: 'created_at')]
    public function index(): AnonymousResourceCollection
    {
        $perPageParam = request()->per_page;
        $pageParam = request()->page;

        // Ensure proper type handling before casting
        $perPage = is_numeric($perPageParam) && (int) $perPageParam !== 0 ? (int) $perPageParam : 15;
        $page = is_numeric($pageParam) && (int) $pageParam !== 0 ? (int) $pageParam : 1;

        $resource = $this->tagService->all(
            $perPage,
            $page,
            ['articles', 'financer'],
        );

        return TagResource::collection($resource);
    }

    /**
     * Show tag.
     *

     *
     * @return TagResource|JsonResponse
     */
    #[RequiresPermission(PermissionDefaults::READ_TAG)]
    public function show(string $id)
    {
        try {
            return new TagResource($this->tagService->find($id, ['articles', 'financer']));
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Tag not found'], 404);
        }
    }

    /**
     * Store tag.
     *
     * Note: financer_id is automatically set from activeFinancerID()
     * and should not be provided in the request.
     */
    #[RequiresPermission(PermissionDefaults::CREATE_TAG)]
    public function store(TagFormRequest $request): TagResource
    {
        $validatedData = $request->validated();

        // Auto-assign financer_id from activeFinancerID()
        $validatedData['financer_id'] = activeFinancerID();

        try {
            $tag = $this->createTagAction->handle($validatedData);
        } catch (Exception $e) {
            Log::error('Error creating tag', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return new TagResource($tag);
    }

    /**
     * Update tag.
     *
     * Note: financer_id is automatically set from activeFinancerID()
     * and should not be provided in the request.
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_TAG)]
    public function update(TagFormRequest $request, string $id): TagResource|JsonResponse
    {
        $validatedData = $request->validated();

        // Auto-assign financer_id from activeFinancerID()
        $validatedData['financer_id'] = activeFinancerID();

        try {
            $tag = $this->tagService->find($id);
            $tag = $this->updateTagAction->handle($tag, $validatedData);

            return new TagResource($tag);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Tag not found'], 404);
        } catch (Exception $e) {
            Log::error('Error updating tag', [
                'tag_id' => $id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete tag.
     */
    #[RequiresPermission(PermissionDefaults::DELETE_TAG)]
    public function destroy(string $id): Response
    {
        // Check if tag exists (HasFinancer scope auto-filters by financer_id)
        $tag = Tag::find($id);

        if (! $tag) {
            return response()->json(['error' => 'Tag not found'], 404);
        }

        return response()->json(['success' => $this->deleteTagAction->handle($tag)])->setStatusCode(204);
    }
}
