<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Question;

use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Services\SurveyDefaultDataService;
use App\Models\Financer;

/**
 * Creates default questions for themes from configuration.
 */
class CreateDefaultQuestionsAction
{
    public function __construct(
        protected SurveyDefaultDataService $surveyDefaultDataService
    ) {}

    /**
     * Execute the action to create questions for themes.
     *
     * @param  array<string, string>  $slugToThemeId  Mapping of theme slug to theme ID
     */
    public function execute(Financer $financer, array $slugToThemeId): void
    {
        $themes = $this->surveyDefaultDataService->getThemes();

        collect($themes)
            ->each(function (array $theme) use ($financer, $slugToThemeId): void {
                $themeId = $slugToThemeId[$theme['slug']] ?? null;

                if (! $themeId) {
                    return;
                }

                foreach ($theme['question_slugs'] as $questionSlug) {
                    if ($question = $this->surveyDefaultDataService->getQuestionBySlug($questionSlug)) {
                        $this->createQuestion($financer, $themeId, $question);
                    }
                }
            });
    }

    protected function createQuestion(Financer $financer, string $themeId, array $questionData): Question
    {
        $createdQuestion = Question::create([
            'financer_id' => $financer->id,
            'theme_id' => $themeId,
            'text' => $questionData['text'],
            'type' => $questionData['type'],
            'is_default' => true,
        ]);

        if (! empty($questionData['options']) && is_array($questionData['options'])) {
            $createdQuestion->options()->createMany($questionData['options']);
        }

        return $createdQuestion;
    }
}
