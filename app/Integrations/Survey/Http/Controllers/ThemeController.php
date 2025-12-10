<?php

namespace App\Integrations\Survey\Http\Controllers;

use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Integrations\Survey\Actions\Theme\DeleteThemeAction;
use App\Integrations\Survey\Actions\Theme\UpdateThemeAction;
use App\Integrations\Survey\Http\Requests\Theme\IndexThemeRequest;
use App\Integrations\Survey\Http\Requests\Theme\UpdateThemeRequest;
use App\Integrations\Survey\Http\Resources\ThemeResource;
use App\Integrations\Survey\Models\Theme;
use App\Integrations\Survey\Pipelines\FilterPipelines\ThemePipeline;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Modules/Survey/Themes
 *
 * @authenticated
 */
#[Group('Modules/Survey/Themes')]
class ThemeController extends Controller
{
    public function __construct(
        private UpdateThemeAction $updateThemeAction
    ) {
        $this->authorizeResource(Theme::class, 'theme');
    }

    /**
     * List themes
     *
     * Return a list of themes with optional filters.
     */
    #[QueryParameter('search', description: 'Search global on differents fields (name, description)', type: 'string', example: 'laravel')]
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('name', description: 'Filter by theme name.', type: 'string', example: 'Theme 1')]
    #[QueryParameter('description', description: 'Filter by theme description.', type: 'string', example: 'Theme 1 description')]
    #[QueryParameter('is_default', description: 'Filter by is default.', type: 'boolean', example: true)]
    #[QueryParameter('created_at', description: 'Filter by creation date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('updated_at', description: 'Filter by update date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('deleted_at', description: 'Filter by deletion date (for soft deleted items).', type: 'date', example: '2024-01-01')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field default name.', type: 'string', example: 'name')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field default name.', type: 'string', example: 'name')]
    public function index(IndexThemeRequest $request): AnonymousResourceCollection
    {
        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        $themes = Theme::query()
            ->withCount(['defaultQuestions'])
            ->with(['defaultQuestions'])
            ->pipe(function ($query) {
                return resolve(ThemePipeline::class)->apply($query);
            })
            ->paginate($perPage);

        return ThemeResource::collection($themes);
    }

    /**
     * Create theme
     */
    public function store(UpdateThemeRequest $request): ThemeResource
    {
        $theme = $this->updateThemeAction->execute(new Theme, $request->validated());

        return new ThemeResource($theme->load('defaultQuestions')->loadCount('defaultQuestions'));
    }

    /**
     * Show theme
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function show(Theme $theme): ThemeResource
    {
        return new ThemeResource($theme->load('defaultQuestions')->loadCount('defaultQuestions'));
    }

    /**
     * Update theme
     */
    public function update(UpdateThemeRequest $request, Theme $theme, UpdateThemeAction $updateThemeAction): ThemeResource
    {
        $theme = $updateThemeAction->execute($theme, $request->validated());

        return new ThemeResource($theme->load('defaultQuestions')->loadCount('defaultQuestions'));
    }

    /**
     * Delete theme
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function destroy(Theme $theme, DeleteThemeAction $deleteThemeAction): Response
    {
        return response()->json(['success' => $deleteThemeAction->execute($theme)])->setStatusCode(204);
    }
}
