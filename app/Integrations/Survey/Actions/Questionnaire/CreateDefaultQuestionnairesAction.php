<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Questionnaire;

use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Questionnaire;
use App\Integrations\Survey\Services\SurveyDefaultDataService;
use App\Models\Financer;

/**
 * Creates default questionnaires for a financer from configuration.
 */
class CreateDefaultQuestionnairesAction
{
    public function __construct(
        protected SurveyDefaultDataService $surveyDefaultDataService
    ) {}

    /**
     * Execute the action to create questionnaires with their questions.
     *
     * @param  array<string, string>  $slugToThemeId  Mapping of theme slug to theme ID
     */
    public function execute(Financer $financer, array $slugToThemeId): void
    {
        $questionnaires = $this->surveyDefaultDataService->getQuestionnaires();

        collect($questionnaires)
            ->each(function (array $questionnaireData) use ($financer, $slugToThemeId): void {
                $this->createQuestionnaireWithQuestions($financer, $questionnaireData, $slugToThemeId);
            });
    }

    protected function createQuestionnaireWithQuestions(Financer $financer, array $questionnaireData, array $slugToThemeId): void
    {
        $questionnaire = Questionnaire::create([
            'financer_id' => $financer->id,
            'name' => $questionnaireData['name'],
            'description' => $questionnaireData['description'],
            'is_default' => true,
        ]);

        $position = 0;
        foreach ($questionnaireData['question_slugs'] as $questionSlug) {
            if ($question = $this->surveyDefaultDataService->getQuestionBySlug($questionSlug)) {
                $themeId = $slugToThemeId[$question['theme_slug']] ?? null;

                if (! $themeId) {
                    continue;
                }

                $createdQuestion = $this->createQuestion($financer, $themeId, $question);
                $questionnaire->questions()->attach($createdQuestion->id, ['position' => $position]);
                $position++;
            }
        }
    }

    protected function createQuestion(Financer $financer, string $themeId, array $questionData): Question
    {
        $createdQuestion = Question::create([
            'financer_id' => $financer->id,
            'theme_id' => $themeId,
            'text' => $questionData['text'],
            'type' => $questionData['type'],
            'is_default' => false,
        ]);

        if (! empty($questionData['options']) && is_array($questionData['options'])) {
            $createdQuestion->options()->createMany($questionData['options']);
        }

        return $createdQuestion;
    }
}
