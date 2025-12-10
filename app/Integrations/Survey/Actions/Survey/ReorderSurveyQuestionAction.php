<?php

namespace App\Integrations\Survey\Actions\Survey;

use App\Integrations\Survey\Actions\AbstractReorderQuestionAction;
use App\Integrations\Survey\Models\Survey;

class ReorderSurveyQuestionAction extends AbstractReorderQuestionAction
{
    /** @param array<int, string>|array<string, array{position: int}|array<int, array{id: string, position: int}>> $data */
    public function execute(Survey $survey, array $data): Survey
    {
        /** @var array<int, array{id: string, position: int}> $questions */
        $questions = $data['questions'] ?? [];
        /** @var Survey $result */
        $result = $this->reorderQuestions($survey, $questions);

        return $result;
    }
}
