<?php

namespace App\Integrations\Survey\Actions\Questionnaire;

use App\Integrations\Survey\Models\Questionnaire;

class ArchiveQuestionnaireAction
{
    public function execute(Questionnaire $questionnaire): Questionnaire
    {
        $questionnaire->archived_at = now();
        $questionnaire->save();

        return $questionnaire->refresh();
    }
}
