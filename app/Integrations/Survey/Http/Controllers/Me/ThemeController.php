<?php

namespace App\Integrations\Survey\Http\Controllers\Me;

use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Integrations\Survey\Http\Requests\Me\Theme\IndexThemeRequest;
use App\Integrations\Survey\Http\Resources\ThemeResource;
use App\Integrations\Survey\Models\Theme;
use App\Integrations\Survey\Pipelines\FilterPipelines\ThemePipeline;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * @group Modules/Survey/Themes
 *
 * @authenticated
 */
#[Group('Me/Modules/Survey/Themes')]
class ThemeController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Theme::class, 'theme');
    }

    /**
     * List themes
     *
     * Return a list of themes with optional filters.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field default name.', type: 'string', example: 'name')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field default name.', type: 'string', example: 'name')]
    public function index(IndexThemeRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Theme::class);

        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        $user = Auth::user();

        if ($user === null) {
            return ThemeResource::collection(Theme::query()->whereRaw('1 = 0')->paginate($perPage));
        }

        /** @phpstan-ignore method.notFound */
        $activeSurveys = $user->surveys()->active()->get();

        $themes = Theme::query()
            ->whereHas('questions', function ($query) use ($activeSurveys): void {
                $query->whereHas('surveys', function ($subQuery) use ($activeSurveys): void {
                    $subQuery->whereIn('int_survey_surveys.id', $activeSurveys->pluck('id'));
                });
            })
            ->pipe(function ($query) {
                return resolve(ThemePipeline::class)->apply($query);
            })
            ->paginate($perPage);

        return ThemeResource::collection($themes);
    }
}
