<?php

namespace App\Integrations\Survey\Actions\Questionnaire;

use App\Integrations\Survey\Models\Questionnaire;

class UnarchiveQuestionnaireAction
{
    public function execute(Questionnaire $questionnaire): Questionnaire
    {
        $questionnaire->archived_at = null;
        $questionnaire->save();

        return $questionnaire->refresh();
    }
}
