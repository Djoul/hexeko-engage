<?php

namespace App\Integrations\Survey\Actions\Questionnaire;

use App\Integrations\Survey\Actions\AbstractLinkQuestionAction;
use App\Integrations\Survey\Models\Questionnaire;

class LinkQuestionnaireQuestionAction extends AbstractLinkQuestionAction
{
    /**
     * Link questions to a questionnaire.
     *
     * When linking questions to a questionnaire, we need to check if each question
     * is already linked to the questionnaire. If the question is not yet linked to
     * the questionnaire, then we must duplicate the question first, then link it.
     * This ensures that each questionnaire has its own copy of questions, allowing
     * for independent modifications without affecting other questionnaires.
     *
     * This is the intended behavior for the link functionality.
     *
     * @param  array<string, array<int|string, array{id: string, position?: int}|string>>  $data
     */
    public function execute(Questionnaire $questionnaire, array $data): Questionnaire
    {
        $questions = $data['questions'] ?? [];

        /** @var Questionnaire $result */
        $result = $this->linkQuestions($questionnaire, $questions);

        return $result;
    }
}
