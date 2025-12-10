<?php

namespace App\Integrations\Survey\Actions\Questionnaire;

use App\Integrations\Survey\Actions\AbstractReorderQuestionAction;
use App\Integrations\Survey\Models\Questionnaire;

class ReorderQuestionnaireQuestionAction extends AbstractReorderQuestionAction
{
    /** @param array<int, string>|array<string, array{position: int}|array<int, array{id: string, position: int}>> $data */
    public function execute(Questionnaire $questionnaire, array $data): Questionnaire
    {
        /** @var array<int, array{id: string, position: int}> $questions */
        $questions = $data['questions'] ?? [];
        /** @var Questionnaire $result */
        $result = $this->reorderQuestions($questionnaire, $questions);

        return $result;
    }
}
