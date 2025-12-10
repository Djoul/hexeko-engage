<?php

namespace App\Integrations\Survey\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Integrations\Survey\Http\Requests\Survey\IndexSurveyMetricRequest;
use App\Integrations\Survey\Http\Requests\Survey\ShowSurveyMetricRequest;
use App\Integrations\Survey\Models\Survey;
use App\Integrations\Survey\Services\SurveyMetricService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group Modules/Survey/Surveys/Metrics
 *
 * @authenticated
 */
#[Group('Modules/Survey/Surveys/Metrics')]
class SurveyMetricController extends Controller
{
    public function __construct(
        private SurveyMetricService $surveyMetricService
    ) {}

    /**
     * Global metrics
     *
     * Return global metrics for a financer.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function index(IndexSurveyMetricRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Survey::class);

        $metrics = $this->surveyMetricService->getGlobalMetrics(
            $request->validated('start_date'),
            $request->validated('end_date')
        );

        return response()->json([
            'data' => $metrics,
        ]);
    }

    /**
     * Survey metrics
     *
     * Return metrics for a survey.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function show(ShowSurveyMetricRequest $request, Survey $survey): JsonResponse
    {
        Gate::authorize('view', $survey);

        $metrics = $this->surveyMetricService->getSurveyMetrics(
            $request->validated('start_date'),
            $request->validated('end_date'),
            $survey->id
        );

        return response()->json([
            'data' => $metrics,
        ]);
    }
}
