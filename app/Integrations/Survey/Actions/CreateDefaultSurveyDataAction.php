<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions;

use App\Integrations\Survey\Actions\Question\CreateDefaultQuestionsAction;
use App\Integrations\Survey\Actions\Questionnaire\CreateDefaultQuestionnairesAction;
use App\Integrations\Survey\Actions\Theme\CreateDefaultThemesAction;
use App\Models\Financer;

/**
 * Orchestrates the creation of default survey data from configuration files.
 *
 * This action delegates to specialized actions for creating themes, questions, and questionnaires.
 */
class CreateDefaultSurveyDataAction
{
    public function __construct(
        protected CreateDefaultThemesAction $createDefaultThemesAction,
        protected CreateDefaultQuestionsAction $createDefaultQuestionsAction,
        protected CreateDefaultQuestionnairesAction $createDefaultQuestionnairesAction
    ) {}

    public function execute(Financer $financer): void
    {
        $slugToThemeId = $this->createDefaultThemesAction->execute($financer);

        $this->createDefaultQuestionsAction->execute($financer, $slugToThemeId);

        $this->createDefaultQuestionnairesAction->execute($financer, $slugToThemeId);
    }
}
