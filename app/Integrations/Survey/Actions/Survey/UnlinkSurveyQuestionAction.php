<?php

namespace App\Integrations\Survey\Actions\Survey;

use App\Integrations\Survey\Models\Survey;

class UnlinkSurveyQuestionAction
{
    /** @param array<string, array<int, string>> $data */
    public function execute(Survey $survey, array $data): Survey
    {
        $questions = $data['questions'] ?? [];
        $survey->questions()->detach($questions);

        return $survey->refresh();
    }
}
