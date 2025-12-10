<?php

namespace App\Integrations\Survey\Actions\Question;

use App\Integrations\Survey\Models\Question;

class ArchiveQuestionAction
{
    public function execute(Question $question): Question
    {
        $question->archived_at = now();
        $question->save();

        return $question->refresh();
    }
}
