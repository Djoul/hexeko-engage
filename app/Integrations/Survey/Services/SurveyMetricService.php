<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Services;

use App\DTOs\Financer\IMetricDTO;
use App\Http\Resources\Api\V1\IMetricResource;
use App\Integrations\Survey\Enums\SurveyStatusEnum;
use App\Integrations\Survey\Models\Submission;
use App\Integrations\Survey\Models\Survey;
use App\Integrations\Survey\Models\SurveyUser;
use Carbon\Month;
use Carbon\WeekDay;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class SurveyMetricService
{
    /**
     * Get global metrics across all surveys within a date range
     * Automatically filtered by the active financer via HasFinancerScope
     *
     * Metrics are weighted: each survey invitation counts independently.
     * If a user is invited to 3 surveys, that counts as 3 invitations.
     *
     * @param  string|null  $startDate  Start date (inclusive)
     * @param  string|null  $endDate  End date (inclusive)
     * @return array{completion_rate: float, response_rate: float, total_surveys: int, total_invitations: int, total_completions: int, total_submissions: int, unique_invited_users: int, unique_completed_users: int, unique_responded_users: int}
     */
    public function getGlobalMetrics(?string $startDate, ?string $endDate): array
    {
        return $this->getMetrics($startDate, $endDate);
    }

    /**
     * Calculate metrics for the previous period
     */
    protected function getPreviousPeriodMetrics(string $startDate, string $endDate): array
    {
        // Calculate the duration of the period
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $durationInDays = $start->diffInDays($end);

        // Calculate previous period dates
        $previousEndDate = $start->copy()->subDay()->toDateString();
        $previousStartDate = $start->copy()->subDays($durationInDays + 1)->toDateString();

        // Get metrics for previous period
        return $this->getMetrics($previousStartDate, $previousEndDate);
    }

    /**
     * Calculate progression percentage between two values
     *
     * @param  float  $oldValue  Previous period value
     * @param  float  $newValue  Current period value
     * @return float Progression in percentage (positive = improvement, negative = decline)
     */
    protected function calculateProgression(float $oldValue, float $newValue): float
    {
        // If old value is 0, we can't calculate percentage change
        if ($oldValue == 0) {
            return $newValue > 0 ? 100.0 : 0.0;
        }

        // Calculate percentage change
        $change = (($newValue - $oldValue) / $oldValue) * 100;

        return round($change, 2);
    }

    public function getSurveyMetrics(?string $startDate, ?string $endDate, string $surveyId): array
    {
        $metrics = $this->getMetrics($startDate, $endDate, $surveyId);

        unset($metrics['surveys_count']);
        unset($metrics['draft_surveys_count']);
        unset($metrics['scheduled_surveys_count']);
        unset($metrics['active_surveys_count']);
        unset($metrics['closed_surveys_count']);
        unset($metrics['archived_surveys_count']);

        return array_merge($metrics, []);
    }

    protected function getMetrics(?string $startDate, ?string $endDate, ?string $surveyId = null): array
    {
        $surveysQuery = Survey::query();

        if ($surveyId) {
            $surveysQuery->where('id', $surveyId);
        }

        if ($startDate && $endDate) {
            $surveysQuery->withinPeriod($startDate, $endDate);
        }

        $surveyIds = $surveysQuery->pluck('id')->toArray();

        if (empty($surveyIds)) {
            return [
                'surveys_count' => 0,
                'draft_surveys_count' => 0,
                'scheduled_surveys_count' => 0,
                'active_surveys_count' => 0,
                'active_surveys' => null,
                'closed_surveys_count' => 0,
                'closed_surveys' => null,
                'archived_surveys_count' => 0,
                'submissions_count' => 0,
                'completed_submissions_count' => 0,
                'users_count' => 0,
                'responded_users_count' => 0,
                'completion_rate' => 0.0,
                'completion_rates' => null,
                'response_rate' => 0.0,
                'response_rates' => null,
            ];
        }

        $submissionsQuery = Submission::query()
            ->whereIn('survey_id', $surveyIds);

        $completedSubmissionsCount = Submission::query()
            ->whereIn('survey_id', $surveyIds)
            ->whereNotNull('completed_at')
            ->count();

        $usersCount = SurveyUser::query()
            ->join('int_survey_surveys', 'int_survey_survey_user.survey_id', '=', 'int_survey_surveys.id')
            ->whereIn('int_survey_surveys.id', $surveyIds)
            ->count('int_survey_survey_user.user_id');

        $activeSurveysCount = Survey::query()->active()->whereIn('id', $surveyIds)->count();

        $activeSurveys = null;

        if ($startDate && $endDate && ! empty($surveyIds)) {
            $activeSurveys = $this->getSurveys(SurveyStatusEnum::ACTIVE, $startDate, $endDate, $surveyIds, $activeSurveysCount);
        }

        $closedSurveysCount = Survey::query()->closed()->whereIn('id', $surveyIds)->count();

        $closedSurveys = null;

        if ($startDate && $endDate && ! empty($surveyIds)) {
            $closedSurveys = $this->getSurveys(SurveyStatusEnum::CLOSED, $startDate, $endDate, $surveyIds, $closedSurveysCount);
        }

        $completionRate = $completedSubmissionsCount > 0
            ? round(($completedSubmissionsCount / $usersCount) * 100, 2)
            : 0.0;

        $completionRates = null;

        if ($startDate && $endDate && ! empty($surveyIds)) {
            $completionRates = $this->calculateCompletionRates($startDate, $endDate, $surveyIds, $completionRate);
        }

        $submissionsCount = $submissionsQuery->count();
        $responseRate = $submissionsCount > 0
            ? round(($submissionsCount / $usersCount) * 100, 2)
            : 0.0;

        $responseRates = null;

        // Calculate response rates by day if dates are provided
        if ($startDate && $endDate && ! empty($surveyIds)) {
            $responseRates = $this->calculateResponseRates($startDate, $endDate, $surveyIds, $responseRate);
        }

        $result = [
            'surveys_count' => count($surveyIds),
            'draft_surveys_count' => Survey::query()->where('status', SurveyStatusEnum::DRAFT)->whereIn('id', $surveyIds)->count(),
            'scheduled_surveys_count' => Survey::query()->scheduled()->whereIn('id', $surveyIds)->count(),
            'active_surveys_count' => $activeSurveysCount,
            'active_surveys' => $activeSurveys,
            'closed_surveys_count' => $closedSurveysCount,
            'closed_surveys' => $closedSurveys,
            'archived_surveys_count' => Survey::query()->where('status', SurveyStatusEnum::ARCHIVED)->whereIn('id', $surveyIds)->count(),
            'completion_rate' => $completionRate,
            'completion_rates' => $completionRates,
            'response_rate' => $responseRate,
            'response_rates' => $responseRates,
            'submissions_count' => $submissionsCount,
            'completed_submissions_count' => $completedSubmissionsCount,
            'users_count' => $usersCount,
        ];

        if ($startDate && $endDate) {
            $previousPeriodMetrics = $this->getPreviousPeriodMetrics($startDate, $endDate);

            $result['previous_period'] = [
                'response_rate' => $previousPeriodMetrics['response_rate'],
                'completion_rate' => $previousPeriodMetrics['completion_rate'],
                'submissions_count' => $previousPeriodMetrics['submissions_count'],
                'completed_submissions_count' => $previousPeriodMetrics['completed_submissions_count'],
                'users_count' => $previousPeriodMetrics['users_count'],
            ];

            $result['progression'] = [
                'response_rate' => $this->calculateProgression(
                    $previousPeriodMetrics['response_rate'],
                    $responseRate
                ),
                'completion_rate' => $this->calculateProgression(
                    $previousPeriodMetrics['completion_rate'],
                    $completionRate
                ),
                'submissions_count' => $this->calculateProgression(
                    $previousPeriodMetrics['submissions_count'],
                    $submissionsCount
                ),
                'completed_submissions_count' => $this->calculateProgression(
                    $previousPeriodMetrics['completed_submissions_count'],
                    $completedSubmissionsCount
                ),
            ];
        }

        return $result;
    }

    protected function getSurveys(string $status, ?string $startDate, ?string $endDate, array $surveyIds, int $surveysCount): ?IMetricResource
    {
        $surveys = null;

        if ($startDate && $endDate && $surveyIds !== []) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();

            // Build query based on status
            $query = Survey::query()->whereIn('id', $surveyIds);

            $dateColumn = 'starts_at';

            if ($status === SurveyStatusEnum::ACTIVE) {
                $query->active();
                $dateColumn = 'starts_at';
            } elseif ($status === SurveyStatusEnum::CLOSED) {
                $query->closed();
                $dateColumn = 'ends_at';
            } elseif ($status === SurveyStatusEnum::ARCHIVED) {
                $query->archived();
                $dateColumn = 'archived_at';
            }

            $query->whereBetween($dateColumn, [$start, $end]);

            // Use GROUP BY DATE() to get counts per day in a single query
            // Validate dateColumn to prevent SQL injection
            $allowedDateColumns = ['starts_at', 'ends_at', 'archived_at'];
            $safeDateColumn = in_array($dateColumn, $allowedDateColumns, true) ? $dateColumn : 'starts_at';

            $surveysByDay = $query
                ->selectRaw("DATE({$safeDateColumn}) as date, COUNT(*) as count")
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date')
                ->mapWithKeys(function ($count, DateTimeInterface|WeekDay|Month|string|int|float|null $date): array {
                    return [Carbon::parse($date)->format('Y-m-d') => (int) $count];
                });

            // Generate labels and data for all days in the range (fill missing days with 0)
            $labels = [];
            $data = [];
            $currentDate = $start->copy();

            while ($currentDate->lte($end)) {
                $dateKey = $currentDate->format('Y-m-d');
                $labels[] = $currentDate->format('d/m');
                $data[] = $surveysByDay->get($dateKey, 0);
                $currentDate->addDay();
            }

            $metricDto = IMetricDTO::createSimple(
                title: 'metrics.title.'.$status.'-surveys',
                tooltip: 'metrics.tooltip.'.$status.'-surveys',
                value: $surveysCount,
                labels: $labels,
                data: $data,
            );

            $surveys = new IMetricResource($metricDto);
        }

        return $surveys;
    }

    protected function calculateCompletionRates(?string $startDate, ?string $endDate, array $surveyIds, float $completionRate): ?IMetricResource
    {
        $completionRates = null;

        if ($startDate && $endDate && $surveyIds !== []) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();

            // Calculate total users count once (it doesn't change per day)
            $totalUsersCount = SurveyUser::query()
                ->join('int_survey_surveys', 'int_survey_survey_user.survey_id', '=', 'int_survey_surveys.id')
                ->whereIn('int_survey_surveys.id', $surveyIds)
                ->distinct()
                ->count('int_survey_survey_user.user_id');

            // Use GROUP BY DATE() to get completed submissions count per day in a single query
            $completedSubmissionsByDay = Submission::query()
                ->whereIn('survey_id', $surveyIds)
                ->whereBetween('created_at', [$start, $end])
                ->whereNotNull('completed_at')
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date')
                ->mapWithKeys(function ($count, DateTimeInterface|WeekDay|Month|string|int|float|null $date): array {
                    return [Carbon::parse($date)->format('Y-m-d') => (int) $count];
                });

            // Generate labels and data for all days in the range
            $labels = [];
            $data = [];
            $currentDate = $start->copy();

            while ($currentDate->lte($end)) {
                $dateKey = $currentDate->format('Y-m-d');
                $completedCount = $completedSubmissionsByDay->get($dateKey, 0);
                $dayCompletionRate = $totalUsersCount > 0
                    ? round(($completedCount / $totalUsersCount) * 100, 2)
                    : 0.0;

                $labels[] = $currentDate->format('d/m');
                $data[] = $dayCompletionRate;
                $currentDate->addDay();
            }

            $metricDto = IMetricDTO::createSimple(
                title: 'metrics.title.completion-rate',
                tooltip: 'metrics.tooltip.completion-rate',
                value: $completionRate,
                labels: $labels,
                data: $data,
                unit: 'metrics.unit.percentage'
            );

            $completionRates = new IMetricResource($metricDto);
        }

        return $completionRates;
    }

    protected function calculateResponseRates(?string $startDate, ?string $endDate, array $surveyIds, float $responseRate): ?IMetricResource
    {
        $responseRates = null;

        if ($startDate && $endDate && $surveyIds !== []) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();

            // Calculate total users count once (it doesn't change per day)
            $totalUsersCount = SurveyUser::query()
                ->join('int_survey_surveys', 'int_survey_survey_user.survey_id', '=', 'int_survey_surveys.id')
                ->whereIn('int_survey_surveys.id', $surveyIds)
                ->distinct()
                ->count('int_survey_survey_user.user_id');

            // Use GROUP BY DATE() to get submissions count per day in a single query
            $submissionsByDay = Submission::query()
                ->whereIn('survey_id', $surveyIds)
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date')
                ->mapWithKeys(function ($count, DateTimeInterface|WeekDay|Month|string|int|float|null $date): array {
                    return [Carbon::parse($date)->format('Y-m-d') => (int) $count];
                });

            // Generate labels and data for all days in the range
            $labels = [];
            $data = [];
            $currentDate = $start->copy();

            while ($currentDate->lte($end)) {
                $dateKey = $currentDate->format('Y-m-d');
                $submissionsCount = $submissionsByDay->get($dateKey, 0);
                $dayResponseRate = $totalUsersCount > 0
                    ? round(($submissionsCount / $totalUsersCount) * 100, 2)
                    : 0.0;

                $labels[] = $currentDate->format('d/m');
                $data[] = $dayResponseRate;
                $currentDate->addDay();
            }

            $metricDto = IMetricDTO::createSimple(
                title: 'metrics.title.response-rate',
                tooltip: 'metrics.tooltip.response-rate',
                value: $responseRate,
                labels: $labels,
                data: $data,
                unit: 'metrics.unit.percentage'
            );

            $responseRates = new IMetricResource($metricDto);
        }

        return $responseRates;
    }
}
