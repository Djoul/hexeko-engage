<?php

namespace App\Integrations\Survey\Http\Controllers;

use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Integrations\Survey\Actions\Theme\AttachThemeQuestionAction;
use App\Integrations\Survey\Actions\Theme\DetachThemeQuestionAction;
use App\Integrations\Survey\Actions\Theme\SyncThemeQuestionAction;
use App\Integrations\Survey\Http\Requests\Question\IndexQuestionRequest;
use App\Integrations\Survey\Http\Requests\Theme\UpdateThemeQuestionRequest;
use App\Integrations\Survey\Http\Resources\QuestionResource;
use App\Integrations\Survey\Http\Resources\ThemeResource;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Theme;
use App\Integrations\Survey\Pipelines\FilterPipelines\ThemeQuestionPipeline;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Modules/Survey/Themes/Questions
 *
 * @authenticated
 */
#[Group('Modules/Survey/Themes/Questions')]
class ThemeQuestionController extends Controller
{
    public function __construct(
        private AttachThemeQuestionAction $attachThemeQuestionAction,
        private DetachThemeQuestionAction $detachThemeQuestionAction,
        private SyncThemeQuestionAction $syncThemeQuestionAction
    ) {}

    /**
     * List questions
     *
     * Return a list of questions with optional filters.
     */
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
    public function index(IndexQuestionRequest $request, Theme $theme): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', $theme);

        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        /** @var Builder<Question> $questionsQuery */
        $questionsQuery = $theme->defaultQuestions()
            ->with(['theme', 'options']);

        $questions = $questionsQuery
            ->pipe(function ($query) {
                return resolve(ThemeQuestionPipeline::class)->apply($query);
            })

            ->paginate($perPage);

        return QuestionResource::collection($questions->sortBy('text'));
    }

    /**
     * Attach questions to theme
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function attach(UpdateThemeQuestionRequest $request, Theme $theme): ThemeResource
    {
        Gate::authorize('update', $theme);

        /** @var array<string, array<int|string, array{id: string}|string>> $validatedData */
        $validatedData = $request->validated();
        $theme = $this->attachThemeQuestionAction->execute($theme, $validatedData);

        return new ThemeResource($theme->load('defaultQuestions'));
    }

    /**
     * Detach questions from theme
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function detach(UpdateThemeQuestionRequest $request, Theme $theme): ThemeResource
    {
        Gate::authorize('update', $theme);

        /** @var array<string, array<int|string, array{id: string}|string>> $validatedData */
        $validatedData = $request->validated();
        $theme = $this->detachThemeQuestionAction->execute($theme, $validatedData);

        return new ThemeResource($theme->load('defaultQuestions'));
    }

    /**
     * Sync questions to theme
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function sync(UpdateThemeQuestionRequest $request, Theme $theme): ThemeResource
    {
        Gate::authorize('update', $theme);

        /** @var array<string, array<int|string, array{id: string}|string>> $validatedData */
        $validatedData = $request->validated();
        $theme = $this->syncThemeQuestionAction->execute($theme, $validatedData);

        return new ThemeResource($theme->load('defaultQuestions'));
    }
}
