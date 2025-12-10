<?php

namespace App\Integrations\Survey\Http\Controllers;

use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Integrations\Survey\Actions\Survey\ArchiveSurveyAction;
use App\Integrations\Survey\Actions\Survey\CreateSurveyAction;
use App\Integrations\Survey\Actions\Survey\DraftSurveyAction;
use App\Integrations\Survey\Actions\Survey\UnarchiveSurveyAction;
use App\Integrations\Survey\Actions\Survey\UpdateSurveyAction;
use App\Integrations\Survey\Http\Requests\Survey\CreateSurveyRequest;
use App\Integrations\Survey\Http\Requests\Survey\DraftSurveyRequest;
use App\Integrations\Survey\Http\Requests\Survey\IndexSurveyRequest;
use App\Integrations\Survey\Http\Requests\Survey\UpdateSurveyRequest;
use App\Integrations\Survey\Http\Resources\SurveyResource;
use App\Integrations\Survey\Models\Survey;
use App\Integrations\Survey\Pipelines\FilterPipelines\SurveyPipeline;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Modules/Survey/Surveys
 *
 * @authenticated
 */
#[Group('Modules/Survey/Surveys')]
class SurveyController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Survey::class, 'survey');
    }

    /**
     * List surveys
     *
     * Return a list of surveys with optional filters.
     */
    #[QueryParameter('search', description: 'Search global on differents fields (title, description)', type: 'string', example: 'laravel')]
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('status', description: 'Filter by status (draft, scheduled, active, closed, archived).', type: 'string', example: 'active')]
    #[QueryParameter('date_from', description: 'Filter by survey start date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('date_to', description: 'Filter by survey start date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('created_at', description: 'Filter by creation date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('updated_at', description: 'Filter by update date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('deleted_at', description: 'Filter by deletion date (for soft deleted items).', type: 'date', example: '2024-01-01')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field default created_at.', type: 'string', example: 'created_at')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field default created_at.', type: 'string', example: 'created_at')]
    public function index(IndexSurveyRequest $request): AnonymousResourceCollection
    {
        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        $surveys = Survey::query()
            ->withCount(['questions'])
            ->with(['questions' => function ($query): void {
                $query->with(['answers'])->withCount(['answers']);
            }])
            ->pipe(function ($query) {
                return resolve(SurveyPipeline::class)->apply($query);
            })
            ->paginate($perPage);

        return SurveyResource::collection($surveys);
    }

    /**
     * Create survey
     */
    public function store(CreateSurveyRequest $request, CreateSurveyAction $createSurveyAction): SurveyResource
    {
        $survey = $createSurveyAction->execute($request->validated());

        return new SurveyResource($survey->load(['questions'])->loadCount(['questions']));
    }

    /**
     * Create draft survey
     */
    public function draft(DraftSurveyRequest $request, DraftSurveyAction $draftSurveyAction): SurveyResource
    {
        Gate::authorize('create', Survey::class);

        $survey = $draftSurveyAction->execute($request->validated());

        return new SurveyResource($survey->load(['questions'])->loadCount(['questions']));
    }

    /**
     * Show survey
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function show(string $survey): SurveyResource
    {
        $survey = Survey::query()->withArchived()->where('id', $survey)->firstOrFail();

        Gate::authorize('view', $survey);

        return new SurveyResource($survey->load(['questions.theme', 'questions.options', 'questions' => function ($query): void {
            $query->with(['answers'])->withCount(['answers']);
        }])->loadCount(['questions', 'submissions']));
    }

    /**
     * Update survey
     */
    public function update(UpdateSurveyRequest $request, Survey $survey, UpdateSurveyAction $updateSurveyAction): SurveyResource
    {
        $survey = $updateSurveyAction->execute($survey, $request->validated());

        return new SurveyResource($survey->load(['questions'])->loadCount('questions'));
    }

    /**
     * Delete survey
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function destroy(Survey $survey): Response
    {
        return response()->json(['success' => $survey->delete()])->setStatusCode(204);
    }

    /**
     * Archive survey
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function archive(Survey $survey, ArchiveSurveyAction $archiveSurveyAction): SurveyResource
    {
        Gate::authorize('update', $survey);

        $survey = $archiveSurveyAction->execute($survey);

        return new SurveyResource($survey->load(['questions'])->loadCount('questions'));
    }

    /**
     * Unarchive survey
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function unarchive(string $survey, UnarchiveSurveyAction $unarchiveSurveyAction): SurveyResource
    {
        $survey = Survey::withoutGlobalScopes()->where('id', $survey)->firstOrFail();

        Gate::authorize('update', $survey);

        $survey = $unarchiveSurveyAction->execute($survey);

        return new SurveyResource($survey->load(['questions'])->loadCount('questions'));
    }
}
