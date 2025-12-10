<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Survey;

use App\Integrations\Survey\Models\Survey;

class ArchiveSurveyAction
{
    public function execute(Survey $survey): Survey
    {
        $survey->archived_at = now();
        $survey->save();

        return $survey->refresh();
    }
}
