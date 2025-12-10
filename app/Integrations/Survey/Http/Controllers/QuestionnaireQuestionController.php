<?php

namespace App\Integrations\Survey\Http\Controllers;

use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Integrations\Survey\Actions\Questionnaire\LinkQuestionnaireQuestionAction;
use App\Integrations\Survey\Actions\Questionnaire\ReorderQuestionnaireQuestionAction;
use App\Integrations\Survey\Actions\Questionnaire\UnlinkQuestionnaireQuestionAction;
use App\Integrations\Survey\Http\Requests\Question\IndexQuestionRequest;
use App\Integrations\Survey\Http\Requests\Questionnaire\LinkQuestionnaireQuestionRequest;
use App\Integrations\Survey\Http\Requests\Questionnaire\ReorderQuestionnaireQuestionRequest;
use App\Integrations\Survey\Http\Requests\Questionnaire\UnlinkQuestionnaireQuestionRequest;
use App\Integrations\Survey\Http\Resources\QuestionnaireResource;
use App\Integrations\Survey\Http\Resources\QuestionResource;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Questionnaire;
use App\Integrations\Survey\Pipelines\FilterPipelines\QuestionnaireQuestionPipeline;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Modules/Survey/Questionnaires/Questions
 *
 * @authenticated
 */
#[Group('Modules/Survey/Questionnaires/Questions')]
class QuestionnaireQuestionController extends Controller
{
    public function __construct(
        private LinkQuestionnaireQuestionAction $linkQuestionnaireQuestionAction,
        private UnlinkQuestionnaireQuestionAction $unlinkQuestionnaireQuestionAction,
        private ReorderQuestionnaireQuestionAction $reorderQuestionnaireQuestionAction
    ) {}

    /**
     * List questions
     *
     * Return a list of questions with optional filters.
     */
    #[QueryParameter('page', description: 'Page number.', type: 'integer', example: '1')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field default position.', type: 'string', example: 'position')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field.', type: 'string', example: 'created_at')]
    public function index(IndexQuestionRequest $request, Questionnaire $questionnaire): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', $questionnaire);

        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        /** @var Builder<Question> $questionsQuery */
        $questionsQuery = $questionnaire->questions()
            ->with(['theme', 'options']);

        $questions = $questionsQuery
            ->pipe(function ($query) {
                return resolve(QuestionnaireQuestionPipeline::class)->apply($query);
            })
            ->paginate($perPage);

        return QuestionResource::collection($questions);
    }

    /**
     * Link questions to questionnaire
     *
     * Link questions to a questionnaire by duplicating them if they're not already linked.
     * This ensures each questionnaire has its own copy of questions for independent modifications.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function link(LinkQuestionnaireQuestionRequest $request, Questionnaire $questionnaire): QuestionnaireResource
    {
        Gate::authorize('update', $questionnaire);

        /** @var array<string, array<int|string, array{id: string, position?: int}|string>> $validatedData */
        $validatedData = $request->validated();
        $questionnaire = $this->linkQuestionnaireQuestionAction->execute($questionnaire, $validatedData);

        return new QuestionnaireResource($questionnaire->load('questions'));
    }

    /**
     * Unlink questions from questionnaire
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function unlink(UnlinkQuestionnaireQuestionRequest $request, Questionnaire $questionnaire): QuestionnaireResource
    {
        Gate::authorize('update', $questionnaire);

        /** @var array<string, array<int, string>> $validatedData */
        $validatedData = $request->validated();
        $questionnaire = $this->unlinkQuestionnaireQuestionAction->execute($questionnaire, $validatedData);

        return new QuestionnaireResource($questionnaire->load('questions'));
    }

    /**
     * Reorder questions in questionnaire
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function reorder(ReorderQuestionnaireQuestionRequest $request, Questionnaire $questionnaire): QuestionnaireResource
    {
        Gate::authorize('update', $questionnaire);

        /** @var array<string, array{position: int}|string> $validatedData */
        $validatedData = $request->validated();
        $questionnaire = $this->reorderQuestionnaireQuestionAction->execute($questionnaire, $validatedData);

        return new QuestionnaireResource($questionnaire->load('questions'));
    }
}
