<?php

namespace App\Integrations\Survey\Http\Controllers;

use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Integrations\Survey\Actions\Survey\LinkSurveyQuestionAction;
use App\Integrations\Survey\Actions\Survey\ReorderSurveyQuestionAction;
use App\Integrations\Survey\Actions\Survey\UnlinkSurveyQuestionAction;
use App\Integrations\Survey\Http\Requests\Survey\IndexSurveyQuestionRequest;
use App\Integrations\Survey\Http\Requests\Survey\LinkSurveyQuestionRequest;
use App\Integrations\Survey\Http\Requests\Survey\ReorderSurveyQuestionRequest;
use App\Integrations\Survey\Http\Requests\Survey\UnlinkSurveyQuestionRequest;
use App\Integrations\Survey\Http\Resources\QuestionResource;
use App\Integrations\Survey\Http\Resources\SurveyResource;
use App\Integrations\Survey\Models\Survey;
use App\Integrations\Survey\Pipelines\FilterPipelines\SurveyQuestionPipeline;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Modules/Survey/Surveys/Questions
 *
 * @authenticated
 */
#[Group('Modules/Survey/Surveys/Questions')]
class SurveyQuestionController extends Controller
{
    public function __construct(
        private LinkSurveyQuestionAction $linkSurveyQuestionAction,
        private UnlinkSurveyQuestionAction $unlinkSurveyQuestionAction,
        private ReorderSurveyQuestionAction $reorderSurveyQuestionAction
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
    public function index(IndexSurveyQuestionRequest $request, Survey $survey): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Survey::class);

        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        $questions = $survey->questions()
            ->with(['theme', 'options'])
            ->withCount('answers')
            ->pipe(function ($query) {
                return resolve(SurveyQuestionPipeline::class)->apply($query);
            })
            ->paginate($perPage);

        return QuestionResource::collection($questions);
    }

    /**
     * Link questions to survey
     *
     * Link questions to a survey by duplicating them if they're not already linked.
     * This ensures each survey has its own copy of questions for independent modifications.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function link(LinkSurveyQuestionRequest $request, Survey $survey): SurveyResource
    {
        Gate::authorize('update', $survey);

        /** @var array<int, string>|array<string, array{position: int}|string|array<int, string>> $validatedData */
        $validatedData = $request->validated();
        $survey = $this->linkSurveyQuestionAction->execute($survey, $validatedData);

        return new SurveyResource($survey->load('questions'));
    }

    /**
     * Unlink questions from survey
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function unlink(UnlinkSurveyQuestionRequest $request, Survey $survey): SurveyResource
    {
        Gate::authorize('update', $survey);

        /** @var array<string, array<int, string>> $validatedData */
        $validatedData = $request->validated();
        $survey = $this->unlinkSurveyQuestionAction->execute($survey, $validatedData);

        return new SurveyResource($survey->load('questions'));
    }

    /**
     * Reorder questions in survey
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function reorder(ReorderSurveyQuestionRequest $request, Survey $survey): SurveyResource
    {
        Gate::authorize('update', $survey);

        /** @var array<int|string, array{position: int}|string> $validatedData */
        $validatedData = $request->validated();
        $survey = $this->reorderSurveyQuestionAction->execute($survey, $validatedData);

        return new SurveyResource($survey->load('questions'));
    }
}
