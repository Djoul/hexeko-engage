<?php

namespace App\Integrations\Survey\Http\Controllers;

use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Integrations\Survey\Actions\Questionnaire\ArchiveQuestionnaireAction;
use App\Integrations\Survey\Actions\Questionnaire\UnarchiveQuestionnaireAction;
use App\Integrations\Survey\Actions\Questionnaire\UpdateQuestionnaireAction;
use App\Integrations\Survey\Http\Requests\Questionnaire\DraftQuestionnaireRequest;
use App\Integrations\Survey\Http\Requests\Questionnaire\IndexQuestionnaireRequest;
use App\Integrations\Survey\Http\Requests\Questionnaire\UpdateQuestionnaireRequest;
use App\Integrations\Survey\Http\Resources\QuestionnaireResource;
use App\Integrations\Survey\Models\Questionnaire;
use App\Integrations\Survey\Pipelines\FilterPipelines\QuestionnairePipeline;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Modules/Survey/Questionnaires
 *
 * @authenticated
 */
#[Group('Modules/Survey/Questionnaires')]
class QuestionnaireController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Questionnaire::class, 'questionnaire');
    }

    /**
     * List questionnaires
     *
     * Return a list of questionnaires with optional filters.
     */
    #[QueryParameter('search', description: 'Search global on differents fields (name, description)', type: 'string', example: 'laravel')]
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('status', description: 'Filter by status (draft, published).', type: 'string', example: 'draft')]
    #[QueryParameter('is_default', description: 'Filter by is default.', type: 'boolean', example: true)]
    #[QueryParameter('created_at', description: 'Filter by creation date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('updated_at', description: 'Filter by update date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('deleted_at', description: 'Filter by deletion date (for soft deleted items).', type: 'date', example: '2024-01-01')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field default position.', type: 'string', example: 'position')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field.', type: 'string', example: 'created_at')]
    public function index(IndexQuestionnaireRequest $request): AnonymousResourceCollection
    {
        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        $questionnaires = Questionnaire::query()
            ->withCount(['questions'])
            ->with(['questions'])
            ->pipe(function ($query) {
                return resolve(QuestionnairePipeline::class)->apply($query);
            })
            ->paginate($perPage);

        return QuestionnaireResource::collection($questionnaires);
    }

    /**
     * Create questionnaire
     */
    public function store(UpdateQuestionnaireRequest $request, UpdateQuestionnaireAction $updateQuestionnaireAction): QuestionnaireResource
    {
        $questionnaire = $updateQuestionnaireAction->execute(new Questionnaire, $request->validated());

        return new QuestionnaireResource($questionnaire->load(['questions'])->loadCount('questions'));
    }

    /**
     * Create draft questionnaire
     */
    public function draft(DraftQuestionnaireRequest $request, UpdateQuestionnaireAction $updateQuestionnaireAction): QuestionnaireResource
    {
        Gate::authorize('create', Questionnaire::class);

        $questionnaire = $updateQuestionnaireAction->execute(new Questionnaire, $request->validated());

        return new QuestionnaireResource($questionnaire->load(['questions'])->loadCount('questions'));
    }

    /**
     * Show questionnaire
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function show(Questionnaire $questionnaire): QuestionnaireResource
    {
        return new QuestionnaireResource($questionnaire->load(['questions', 'questions.theme'])->loadCount('questions'));
    }

    /**
     * Update questionnaire
     */
    public function update(UpdateQuestionnaireRequest $request, Questionnaire $questionnaire, UpdateQuestionnaireAction $updateQuestionnaireAction): QuestionnaireResource
    {
        $questionnaire = $updateQuestionnaireAction->execute($questionnaire, $request->validated());

        return new QuestionnaireResource($questionnaire->load(['questions'])->loadCount('questions'));
    }

    /**
     * Delete questionnaire
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function destroy(Questionnaire $questionnaire): Response
    {
        return response()->json(['success' => $questionnaire->delete()])->setStatusCode(204);
    }

    /**
     * Archive questionnaire
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function archive(Questionnaire $questionnaire, ArchiveQuestionnaireAction $archiveQuestionnaireAction): QuestionnaireResource
    {
        Gate::authorize('update', $questionnaire);

        $questionnaire = $archiveQuestionnaireAction->execute($questionnaire);

        return new QuestionnaireResource($questionnaire->load(['questions'])->loadCount('questions'));
    }

    /**
     * Unarchive questionnaire
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function unarchive(string $questionnaire, UnarchiveQuestionnaireAction $unarchiveQuestionnaireAction): QuestionnaireResource
    {
        $questionnaire = Questionnaire::withoutGlobalScopes()->where('id', $questionnaire)->firstOrFail();

        Gate::authorize('update', $questionnaire);

        $questionnaire = $unarchiveQuestionnaireAction->execute($questionnaire);

        return new QuestionnaireResource($questionnaire->load(['questions'])->loadCount('questions'));
    }
}
