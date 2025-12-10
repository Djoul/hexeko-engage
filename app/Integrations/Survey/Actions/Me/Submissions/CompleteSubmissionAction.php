<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Me\Submissions;

use App\Integrations\Survey\Exceptions\SubmissionException;
use App\Integrations\Survey\Models\Submission;

class CompleteSubmissionAction
{
    public function execute(Submission $submission): Submission
    {
        if ($submission->completed_at !== null) {
            throw SubmissionException::alreadyCompleted();
        }

        if (! $submission->survey->isActive()) {
            throw SubmissionException::surveyNotActive();
        }

        $requiredQuestions = $submission->survey->questions()->count();
        $answeredQuestions = $submission->answers()->count();

        if ($answeredQuestions < $requiredQuestions) {
            throw SubmissionException::incompleteAnswers($answeredQuestions, $requiredQuestions);
        }

        $submission->completed_at = now();
        $submission->save();

        $survey = $submission->survey()->first();

        if ($survey !== null) {
            $survey->forceFill([
                'submissions_count' => $survey->submissions()->count(),
            ])->save();
        }

        return $submission->refresh();
    }
}
