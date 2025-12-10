<?php

namespace App\Integrations\Survey\Actions\Questionnaire;

use App\Integrations\Survey\Models\Questionnaire;

class UnlinkQuestionnaireQuestionAction
{
    /** @param array<string, array<int, string>> $data */
    public function execute(Questionnaire $questionnaire, array $data): Questionnaire
    {
        $questions = $data['questions'] ?? [];
        $questionnaire->questions()->detach($questions);

        return $questionnaire->refresh();
    }
}
