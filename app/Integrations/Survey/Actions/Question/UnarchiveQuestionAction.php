<?php

namespace App\Integrations\Survey\Actions\Question;

use App\Integrations\Survey\Models\Question;

class UnarchiveQuestionAction
{
    public function execute(Question $question): Question
    {
        $question->archived_at = null;
        $question->save();

        return $question->refresh();
    }
}
