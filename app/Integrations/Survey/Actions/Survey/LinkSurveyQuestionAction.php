<?php

namespace App\Integrations\Survey\Actions\Survey;

use App\Integrations\Survey\Actions\AbstractLinkQuestionAction;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Questionnaire;
use App\Integrations\Survey\Models\Survey;

class LinkSurveyQuestionAction extends AbstractLinkQuestionAction
{
    /**
     * Link questions to a survey.
     *
     * When linking questions to a survey, we need to check if each question
     * is already linked to the survey. If the question is not yet linked to
     * the survey, then we must duplicate the question first, then link it.
     * This ensures that each survey has its own copy of questions, allowing
     * for independent modifications without affecting other surveys.
     *
     * This is the intended behavior for the link functionality.
     */
    /** @param array<int, string>|array<string, array{position: int}|string|array<int, string>> $data */
    public function execute(Survey $survey, array $data): Survey
    {
        /** @var array<int|string, array{id: string, position?: int}|string> $questions */
        $questions = $data['questions'] ?? [];
        $questionnaires = $data['questionnaires'] ?? [];

        if ($questionnaires) {
            $questionnaires = Questionnaire::whereIn('id', $questionnaires)->with('questions')->get();
            /** @var array<int, array{id: string, position: int}> $questionsFromQuestionnaires */
            $questionsFromQuestionnaires = [];
            foreach ($questionnaires as $questionnaire) {
                $questionnaireQuestions = $questionnaire->questions;
                foreach ($questionnaireQuestions as $question) {
                    /** @var Question $question */
                    $questionsFromQuestionnaires[] = ['id' => $question->id, 'position' => $question->pivot->position ?? 0];
                }
            }

            /** @var array<int|string, array{id: string, position?: int}|string> $mergedQuestions */
            $mergedQuestions = is_array($questions) ? array_merge($questions, $questionsFromQuestionnaires) : $questionsFromQuestionnaires;
            $questions = $mergedQuestions;
        }

        /** @var Survey $result */
        $result = $this->linkQuestions($survey, $questions);

        return $result;
    }
}
