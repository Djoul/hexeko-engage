<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Me\Submissions;

use App\Integrations\Survey\Exceptions\SubmissionException;
use App\Integrations\Survey\Models\Submission;
use Illuminate\Support\Facades\Auth;

class CreateSubmissionAction
{
    /** @param array<string, mixed> $data */
    public function execute(Submission $submission, array $data): Submission
    {
        if (Submission::query()
            ->where('user_id', (string) Auth::user()?->id)
            ->where('survey_id', $data['survey_id'])
            ->exists()) {
            throw SubmissionException::alreadyExists((string) Auth::user()?->id, $data['survey_id']);
        }

        $submission->fill($data);
        $submission->user_id = (string) Auth::user()?->id;
        $submission->started_at = now();

        $submission->save();

        return $submission->refresh();
    }
}
