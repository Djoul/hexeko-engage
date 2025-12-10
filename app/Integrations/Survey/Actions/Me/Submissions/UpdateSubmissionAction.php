<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Me\Submissions;

use App\Integrations\Survey\Models\Submission;

class UpdateSubmissionAction
{
    /** @param array<string, mixed> $data */
    public function execute(Submission $submission, array $data): Submission
    {
        $submission->fill($data);

        $submission->save();

        return $submission->refresh();
    }
}
