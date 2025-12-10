<?php

namespace App\Integrations\Survey\Actions\Survey;

use App\Integrations\Survey\Models\Survey;

class UnarchiveSurveyAction
{
    public function execute(Survey $survey): Survey
    {
        $survey->archived_at = null;
        $survey->save();

        return $survey->refresh();
    }
}
