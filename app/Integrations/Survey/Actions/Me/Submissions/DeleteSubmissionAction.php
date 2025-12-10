<?php

namespace App\Integrations\Survey\Actions\Me\Submissions;

use App\Integrations\Survey\Models\Submission;

class DeleteSubmissionAction
{
    public function execute(Submission $submission): ?bool
    {
        $submission->answers()->delete();

        $survey = $submission->survey()->first();

        if ($survey !== null) {
            $survey->forceFill([
                'submissions_count' => $survey->submissions()->count(),
            ])->save();
        }

        return $submission->delete();
    }
}
