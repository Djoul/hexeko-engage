<?php

namespace App\Integrations\Survey\Actions\Question;

use App\Integrations\Survey\Models\Question;

class DeleteQuestionAction
{
    public function execute(Question $question): ?bool
    {
        return $question->delete();
    }
}
