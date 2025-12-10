<?php

namespace App\Integrations\Survey\Http\Controllers\Me;

use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Integrations\Survey\Actions\Survey\ToggleFavoriteSurveyAction;
use App\Integrations\Survey\Http\Requests\Me\Survey\IndexSurveyRequest;
use App\Integrations\Survey\Http\Resources\Me\SurveyResource;
use App\Integrations\Survey\Models\Survey;
use App\Integrations\Survey\Pipelines\FilterPipelines\SurveyPipeline;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * @group Modules/Survey/Surveys
 *
 * @authenticated
 */
#[Group('Me/Modules/Survey/Surveys')]
class SurveyController extends Controller
{
    /**
     * List surveys
     *
     * Return a list of surveys with optional filters.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('status', description: 'Filter by status (new, draft, scheduled, active, closed, archived).', type: 'string', example: 'active')]
    #[QueryParameter('user_status', description: 'Filter by user status (open, ongoing, completed).', type: 'mixed', example: 'ongoing')]
    #[QueryParameter('is_favorite', description: 'Filter by favorite status.', type: 'boolean', example: true)]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field default created_at.', type: 'string', example: 'created_at')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field default created_at.', type: 'string', example: 'created_at')]
    public function index(IndexSurveyRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Survey::class);

        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        $user = Auth::user();

        if ($user === null) {
            return SurveyResource::collection(Survey::query()->whereRaw('1 = 0')->paginate($perPage));
        }

        /** @phpstan-ignore method.notFound */
        $surveys = $user->surveys()
            ->with('favorites')
            ->with('submissions')
            ->withCount(['questions'])
            ->pipe(function ($query) {
                return resolve(SurveyPipeline::class)->apply($query);
            })
            ->paginate($perPage);

        return SurveyResource::collection($surveys);
    }

    /**
     * Show survey
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function show(Survey $survey): SurveyResource
    {
        Gate::authorize('view', $survey);

        return new SurveyResource($survey->load(['favorites', 'financer', 'segment', 'questions', 'questions.options', 'submissions'])->loadCount(['questions']));
    }

    /**
     * Toggle favorite status for a survey.
     */
    public function toggleFavorite(Survey $survey, ToggleFavoriteSurveyAction $toggleFavoriteSurveyAction): SurveyResource
    {
        Gate::authorize('view', $survey);

        $survey = $toggleFavoriteSurveyAction->execute($survey);

        return new SurveyResource($survey->load(['financer', 'segment', 'questions'])->loadCount(['questions']));
    }
}
