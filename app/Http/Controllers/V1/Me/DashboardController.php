<?php

namespace App\Http\Controllers\V1\Me;

use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Me\IndexDashboardRequest;
use App\Http\Resources\Me\DashboardResource;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\Survey\Models\Survey;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

#[Group('Me/Dashboard')]
class DashboardController extends Controller
{
    /**
     * List dashboard items
     *
     * Return a list of dashboard items with optional filters.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    public function index(IndexDashboardRequest $request): AnonymousResourceCollection
    {
        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::SMALL;

        $currentLocale = app()->getLocale();
        $user = auth()->user();

        $articles = Article::query()
            ->join('int_communication_rh_article_translations as at', function ($join) use ($currentLocale): void {
                $join->on('int_communication_rh_articles.id', '=', 'at.article_id')
                    ->where('at.language', '=', $currentLocale)
                    ->whereNull('at.deleted_at');
            })
            ->select([
                'int_communication_rh_articles.id',
                'at.title',
                'int_communication_rh_articles.created_at',
                'int_communication_rh_articles.updated_at',
                DB::raw("'article' as type"),
            ])
            ->where('at.status', 'published')
            ->where(function ($query) use ($user): void {
                $query->whereIn('int_communication_rh_articles.segment_id', $user->segments->pluck('id')->toArray())
                    ->orWhereNull('int_communication_rh_articles.segment_id');
            })
            ->whereNull('int_communication_rh_articles.deleted_at');

        $surveys = $user->surveys()
            ->active()
            ->selectRaw('int_survey_surveys.id, int_survey_surveys.title->>? as title, int_survey_surveys.created_at, int_survey_surveys.updated_at, ? as type', [
                $currentLocale,
                'survey',
            ])
            ->whereNull('int_survey_surveys.archived_at')
            ->whereNull('int_survey_surveys.deleted_at');

        $dashboardItems = $articles
            ->union($surveys)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $collection = $dashboardItems->getCollection();

        $articleIds = $collection->where('type', 'article')->pluck('id')->all();
        $surveyIds = $collection->where('type', 'survey')->pluck('id')->all();

        $articlesMap = empty($articleIds)
            ? collect()
            : Article::with(['translations', 'financer', 'author'])
                ->whereIn('id', $articleIds)
                ->get()
                ->keyBy('id');

        $surveysMap = empty($surveyIds)
            ? collect()
            : Survey::withCount(['questions'])
                ->whereIn('id', $surveyIds)
                ->get()
                ->keyBy('id');

        $collection->transform(function ($item) use ($articlesMap, $surveysMap) {
            $item->model = $item->type === 'article' ? $articlesMap->get($item->id) : $surveysMap->get($item->id);

            return $item;
        });

        return DashboardResource::collection($dashboardItems);
    }
}
