<?php

namespace App\Integrations\Survey\Http\Controllers;

use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Integrations\Survey\Actions\Question\ArchiveQuestionAction;
use App\Integrations\Survey\Actions\Question\CreateQuestionAction;
use App\Integrations\Survey\Actions\Question\DeleteQuestionAction;
use App\Integrations\Survey\Actions\Question\UnarchiveQuestionAction;
use App\Integrations\Survey\Actions\Question\UpdateQuestionAction;
use App\Integrations\Survey\Http\Requests\Question\CreateQuestionRequest;
use App\Integrations\Survey\Http\Requests\Question\IndexQuestionRequest;
use App\Integrations\Survey\Http\Requests\Question\UpdateQuestionRequest;
use App\Integrations\Survey\Http\Resources\QuestionResource;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Pipelines\FilterPipelines\QuestionPipeline;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Modules/Survey/Questions
 *
 * @authenticated
 */
#[Group('Modules/Survey/Questions')]
class QuestionController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Question::class, 'question');
    }

    /**
     * List questions
     *
     * Return a list of questions with optional filters.
     */
    #[QueryParameter('search', description: 'Search global on differents fields (text)', type: 'string', example: 'laravel')]
    #[QueryParameter('theme_id', description: 'Filter by theme ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('type', description: 'Filter by type.', type: 'string', example: 'text')]
    #[QueryParameter('is_default', description: 'Filter by is default.', type: 'boolean', example: true)]
    #[QueryParameter('created_at', description: 'Filter by creation date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('updated_at', description: 'Filter by update date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('deleted_at', description: 'Filter by deletion date (for soft deleted items).', type: 'date', example: '2024-01-01')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field default position.', type: 'string', example: 'position')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field.', type: 'string', example: 'created_at')]
    public function index(IndexQuestionRequest $request): AnonymousResourceCollection
    {
        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        $questions = Question::query()
            ->with(['theme', 'options'])
            ->pipe(function ($query) {
                return resolve(QuestionPipeline::class)->apply($query);
            })
            ->paginate($perPage);

        return QuestionResource::collection($questions);
    }

    /**
     * Create question
     */
    public function store(CreateQuestionRequest $request, CreateQuestionAction $createQuestionAction): QuestionResource
    {
        $question = $createQuestionAction->execute(new Question, $request->validated());

        return new QuestionResource($question->load(['theme', 'options']));
    }

    /**
     * Show question
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function show(Question $question): QuestionResource
    {
        return new QuestionResource($question->load(['theme', 'options']));
    }

    /**
     * Update question
     */
    public function update(UpdateQuestionRequest $request, Question $question, UpdateQuestionAction $updateQuestionAction): QuestionResource
    {
        $question = $updateQuestionAction->execute($question, $request->validated());

        return new QuestionResource($question->load(['theme', 'options']));
    }

    /**
     * Delete question
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function destroy(Question $question, DeleteQuestionAction $deleteQuestionAction): Response
    {
        return response()->json(['success' => $deleteQuestionAction->execute($question)])->setStatusCode(204);
    }

    /**
     * Archive question
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function archive(Question $question, ArchiveQuestionAction $archiveQuestionAction): QuestionResource
    {
        Gate::authorize('update', $question);

        $question = $archiveQuestionAction->execute($question);

        return new QuestionResource($question->load(['theme', 'options']));
    }

    /**
     * Unarchive question
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function unarchive(string $question, UnarchiveQuestionAction $unarchiveQuestionAction): QuestionResource
    {
        $question = Question::withoutGlobalScopes()->where('id', $question)->firstOrFail();

        Gate::authorize('update', $question);

        $question = $unarchiveQuestionAction->execute($question);

        return new QuestionResource($question->load(['theme', 'options']));
    }
}
